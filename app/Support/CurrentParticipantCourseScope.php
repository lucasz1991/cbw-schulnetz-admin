<?php

namespace App\Support;

use App\Models\Person;
use Illuminate\Support\Carbon;

class CurrentParticipantCourseScope
{
    public static function currentContractOverviewFor(?Person $person): ?array
    {
        if (! $person) {
            return null;
        }

        $programData = is_array($person->programdata) ? $person->programdata : [];
        $statusData = is_array($person->statusdata) ? $person->statusdata : [];
        $activeContract = self::activeContracts($statusData)
            ->sortByDesc(fn (array $contract) => self::parseDate($contract['vertrag_ende'] ?? null)?->timestamp ?? 0)
            ->first();
        $identifiers = self::identifiersFor($person);

        if (! $activeContract && ! self::hasCurrentContractFilter($identifiers) && empty($programData)) {
            return null;
        }

        $gender = strtoupper(trim((string) (data_get($programData, 'geschlecht') ?? $person->geschlecht ?? '')));
        $programTitle = $gender === 'M'
            ? data_get($programData, 'langbez_m')
            : data_get($programData, 'langbez_w');

        $programTitle = self::firstFilled([
            $programTitle,
            data_get($programData, 'langbez_m'),
            data_get($programData, 'langbez_w'),
            data_get($programData, 'massn_kurz'),
        ]);

        $cancelledAt = self::firstFilled([
            data_get($activeContract, 'kuendig_zum'),
            data_get($statusData, 'vertrag_kuendig_zum'),
            data_get($programData, 'kuendig_zum'),
        ]);

        return [
            'person_pk' => $person->id,
            'person_name' => self::firstFilled([
                trim(($person->vorname ?? '') . ' ' . ($person->nachname ?? '')),
                data_get($programData, 'name'),
                $person->person_id,
            ]),
            'person_id' => $person->person_id,
            'status' => self::firstFilled([
                data_get($statusData, 'status'),
                data_get($statusData, 'status_short'),
                $person->status,
            ]),
            'teilnehmer_id' => self::firstFilled([
                data_get($activeContract, 'teilnehmer_id'),
                $identifiers['teilnehmer_id'] ?? null,
            ]),
            'teilnehmer_nr' => self::firstFilled([
                data_get($statusData, 'teilnehmer_nr'),
                data_get($programData, 'teilnehmer_nr'),
                $person->teilnehmer_nr,
            ]),
            'beginn' => self::firstFilled([
                data_get($programData, 'vertrag_beginn'),
                data_get($activeContract, 'beginn'),
            ]),
            'ende' => self::firstFilled([
                data_get($activeContract, 'vertrag_ende'),
                data_get($programData, 'vertrag_ende'),
                data_get($statusData, 'last_teilnehmer_tag'),
            ]),
            'letzter_tag' => self::firstFilled([
                data_get($activeContract, 'letzter_tag'),
                data_get($statusData, 'last_teilnehmer_tag'),
            ]),
            'kuendig_zum' => $cancelledAt,
            'massnahme_id' => self::firstFilled([
                data_get($programData, 'massnahme_id'),
                data_get($programData, 'massn_kurz'),
            ]),
            'stammklasse' => data_get($programData, 'stammklasse'),
            'program_title' => $programTitle,
            'uform' => data_get($programData, 'uform'),
            'vtz' => self::firstFilled([
                data_get($programData, 'vtz_lang'),
                data_get($programData, 'vtz'),
            ]),
            'kostentraeger' => data_get($programData, 'mp_langbez'),
            'is_active' => $activeContract
                ? filter_var($activeContract['is_active'] ?? false, FILTER_VALIDATE_BOOL)
                : null,
        ];
    }

    public static function identifiersFor(?Person $person): array
    {
        if (! $person) {
            return [
                'teilnehmer_id' => null,
                'tn_baustein_ids' => [],
                'klassen_ids' => [],
                'baustein_ids' => [],
            ];
        }

        $programData = is_array($person->programdata) ? $person->programdata : [];
        $statusData = is_array($person->statusdata) ? $person->statusdata : [];
        $activeContract = self::activeContracts($statusData)->first();

        $teilnehmerId = self::firstFilled([
            data_get($activeContract, 'teilnehmer_id'),
            data_get($statusData, 'teilnehmer_id'),
            data_get($programData, 'teilnehmer_id'),
            $person->teilnehmer_id,
        ]);

        $blocks = collect(data_get($programData, 'tn_baust', []))
            ->filter(fn ($block) => is_array($block));

        return [
            'teilnehmer_id' => $teilnehmerId,
            'tn_baustein_ids' => self::cleanIdentifierList($blocks->pluck('tn_baustein_id')->all()),
            'klassen_ids' => self::cleanIdentifierList($blocks->pluck('klassen_id')->all()),
            'baustein_ids' => self::cleanIdentifierList($blocks->pluck('baustein_id')->all()),
        ];
    }

    public static function hasCurrentContractFilter(array $identifiers): bool
    {
        return ! empty($identifiers['teilnehmer_id'])
            || ! empty($identifiers['tn_baustein_ids'])
            || ! empty($identifiers['klassen_ids']);
    }

    public static function applyForPerson($query, Person $person, string $pivotAlias = 'cpe', ?string $courseTable = 'courses'): void
    {
        $query->where($pivotAlias . '.person_id', $person->id);

        $identifiers = self::identifiersFor($person);
        if (! self::hasCurrentContractFilter($identifiers)) {
            return;
        }

        $query->where(function ($current) use ($identifiers, $pivotAlias, $courseTable) {
            $hasParticipantId = ! empty($identifiers['teilnehmer_id']);
            $hasProgramFallback = ! empty($identifiers['tn_baustein_ids']) || ! empty($identifiers['klassen_ids']);

            if ($hasParticipantId) {
                $current->where($pivotAlias . '.teilnehmer_id', $identifiers['teilnehmer_id']);
            }

            if (! $hasProgramFallback) {
                return;
            }

            $legacyProgramMatch = function ($legacy) use ($identifiers, $pivotAlias, $courseTable, $hasParticipantId) {
                if ($hasParticipantId) {
                    $legacy->where(function ($unknownParticipantId) use ($pivotAlias) {
                        $unknownParticipantId
                            ->whereNull($pivotAlias . '.teilnehmer_id')
                            ->orWhere($pivotAlias . '.teilnehmer_id', '');
                    });
                }

                $legacy->where(function ($programMatch) use ($identifiers, $pivotAlias, $courseTable) {
                    $added = false;

                    if (! empty($identifiers['tn_baustein_ids'])) {
                        $programMatch->whereIn($pivotAlias . '.tn_baustein_id', $identifiers['tn_baustein_ids']);
                        $added = true;
                    }

                    if (! empty($identifiers['klassen_ids'])) {
                        if ($added) {
                            $programMatch->orWhereIn($pivotAlias . '.klassen_id', $identifiers['klassen_ids']);
                        } else {
                            $programMatch->whereIn($pivotAlias . '.klassen_id', $identifiers['klassen_ids']);
                            $added = true;
                        }

                        if ($courseTable) {
                            $programMatch->orWhereIn($courseTable . '.klassen_id', $identifiers['klassen_ids']);
                        }
                    }
                });
            };

            if ($hasParticipantId) {
                $current->orWhere($legacyProgramMatch);
            } else {
                $current->where($legacyProgramMatch);
            }
        });
    }

    protected static function activeContracts(array $statusData)
    {
        $today = Carbon::today('Europe/Berlin');

        return collect(data_get($statusData, 'vertraege', []))
            ->filter(fn ($contract) => is_array($contract))
            ->filter(function (array $contract) use ($today) {
                if (! filter_var($contract['is_active'] ?? false, FILTER_VALIDATE_BOOL)) {
                    return false;
                }

                $contractEnd = self::parseDate($contract['vertrag_ende'] ?? null);
                $cancelledAt = self::parseDate($contract['kuendig_zum'] ?? null);

                if ($contractEnd && $contractEnd->endOfDay()->lt($today)) {
                    return false;
                }

                if ($cancelledAt && $cancelledAt->endOfDay()->lt($today)) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    protected static function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) ($value ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected static function cleanIdentifierList(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => trim((string) ($value ?? '')))
            ->filter(fn (string $value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    protected static function parseDate(mixed $value): ?Carbon
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        foreach (['Y/m/d', 'Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $raw, 'Europe/Berlin')->startOfDay();
            } catch (\Throwable) {
                // try next known format
            }
        }

        try {
            return Carbon::parse(str_replace('/', '-', $raw), 'Europe/Berlin')->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
