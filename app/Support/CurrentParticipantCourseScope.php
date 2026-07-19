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
        $activeContract = self::currentContract($statusData);
        $programData = self::programDataForContract($programData, $activeContract);
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
            data_get($programData, 'kuendig_zum'),
            $activeContract ? null : data_get($statusData, 'vertrag_kuendig_zum'),
        ]);

        return [
            'person_pk' => $person->id,
            'person_name' => self::firstFilled([
                trim(($person->vorname ?? '').' '.($person->nachname ?? '')),
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
                data_get($programData, 'teilnehmer_id'),
                $activeContract ? null : ($identifiers['teilnehmer_id'] ?? null),
            ]),
            'teilnehmer_nr' => self::firstFilled([
                data_get($activeContract, 'teilnehmer_nr'),
                data_get($programData, 'teilnehmer_nr'),
                $activeContract ? null : data_get($statusData, 'teilnehmer_nr'),
                $activeContract ? null : $person->teilnehmer_nr,
            ]),
            'beginn' => self::firstFilled([
                data_get($activeContract, 'vertrag_beginn'),
                data_get($activeContract, 'beginn'),
                data_get($programData, 'vertrag_beginn'),
            ]),
            'ende' => self::firstFilled([
                data_get($activeContract, 'vertrag_ende'),
                data_get($programData, 'vertrag_ende'),
                $activeContract ? null : data_get($statusData, 'last_teilnehmer_tag'),
            ]),
            'letzter_tag' => self::firstFilled([
                data_get($activeContract, 'letzter_tag'),
                data_get($programData, 'vertrag_ende'),
                $activeContract ? null : data_get($statusData, 'last_teilnehmer_tag'),
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

    /**
     * Return every known participant contract for the admin profile. Only the
     * selected contract can be enriched from programdata because that payload
     * intentionally contains a single contract.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function contractOverviewsFor(?Person $person): array
    {
        if (! $person) {
            return [];
        }

        $statusData = is_array($person->statusdata) ? $person->statusdata : [];
        $contracts = collect(data_get($statusData, 'vertraege', []))
            ->filter(fn ($contract) => is_array($contract))
            ->values();

        if ($contracts->isEmpty()) {
            $fallback = self::currentContractOverviewFor($person);

            if (! $fallback) {
                return [];
            }

            $fallback['beratung_id'] = null;
            $fallback['is_current'] = ($fallback['is_active'] ?? null) === true;
            $fallback['contract_state'] = $fallback['is_current'] ? 'current' : 'unknown';

            return [$fallback];
        }

        $currentContract = self::currentContract($statusData);
        $currentOverview = self::currentContractOverviewFor($person);
        $personName = self::firstFilled([
            trim(($person->vorname ?? '').' '.($person->nachname ?? '')),
            data_get($person->programdata, 'name'),
            $person->person_id,
        ]);
        $personStatus = self::firstFilled([
            data_get($statusData, 'status'),
            data_get($statusData, 'status_short'),
            $person->status,
        ]);

        return $contracts
            ->map(function (array $contract) use ($person, $currentContract, $currentOverview, $personName, $personStatus) {
                $isCurrent = self::isSelectedContract($contract)
                    || self::sameContract($contract, $currentContract);
                $isActive = filter_var($contract['is_active'] ?? false, FILTER_VALIDATE_BOOL);

                if ($isCurrent && $currentOverview) {
                    return array_replace($currentOverview, [
                        'beratung_id' => self::firstFilled([$contract['beratung_id'] ?? null]),
                        'teilnehmer_id' => self::firstFilled([
                            $contract['teilnehmer_id'] ?? null,
                            $currentOverview['teilnehmer_id'] ?? null,
                        ]),
                        'teilnehmer_nr' => self::firstFilled([
                            $contract['teilnehmer_nr'] ?? null,
                            $currentOverview['teilnehmer_nr'] ?? null,
                        ]),
                        'beginn' => self::firstFilled([
                            $contract['vertrag_beginn'] ?? null,
                            $contract['beginn'] ?? null,
                            $currentOverview['beginn'] ?? null,
                        ]),
                        'ende' => self::firstFilled([
                            $contract['vertrag_ende'] ?? null,
                            $currentOverview['ende'] ?? null,
                        ]),
                        'letzter_tag' => self::firstFilled([
                            $contract['letzter_tag'] ?? null,
                            $currentOverview['letzter_tag'] ?? null,
                        ]),
                        'kuendig_zum' => self::firstFilled([
                            $contract['kuendig_zum'] ?? null,
                            $currentOverview['kuendig_zum'] ?? null,
                        ]),
                        'is_active' => $isActive,
                        'is_current' => true,
                        'contract_state' => 'current',
                    ]);
                }

                return [
                    'person_pk' => $person->id,
                    'person_name' => $personName,
                    'person_id' => $person->person_id,
                    'status' => $personStatus,
                    'beratung_id' => self::firstFilled([$contract['beratung_id'] ?? null]),
                    'teilnehmer_id' => self::firstFilled([$contract['teilnehmer_id'] ?? null]),
                    'teilnehmer_nr' => self::firstFilled([$contract['teilnehmer_nr'] ?? null]),
                    'beginn' => self::firstFilled([
                        $contract['vertrag_beginn'] ?? null,
                        $contract['beginn'] ?? null,
                    ]),
                    'ende' => self::firstFilled([$contract['vertrag_ende'] ?? null]),
                    'letzter_tag' => self::firstFilled([$contract['letzter_tag'] ?? null]),
                    'kuendig_zum' => self::firstFilled([$contract['kuendig_zum'] ?? null]),
                    'massnahme_id' => null,
                    'stammklasse' => null,
                    'program_title' => null,
                    'uform' => null,
                    'vtz' => null,
                    'kostentraeger' => null,
                    'is_active' => $isActive,
                    'is_current' => false,
                    'contract_state' => $isActive ? 'open' : 'closed',
                ];
            })
            ->unique(fn (array $contract) => implode('|', [
                $contract['person_pk'] ?? '',
                $contract['beratung_id'] ?? '',
                $contract['teilnehmer_id'] ?? '',
                $contract['teilnehmer_nr'] ?? '',
                $contract['beginn'] ?? '',
                $contract['ende'] ?? '',
            ]))
            ->sort(function (array $left, array $right) {
                $currentCompare = ((int) ($right['is_current'] ?? false))
                    <=> ((int) ($left['is_current'] ?? false));
                if ($currentCompare !== 0) {
                    return $currentCompare;
                }

                $activeCompare = ((int) ($right['is_active'] ?? false))
                    <=> ((int) ($left['is_active'] ?? false));
                if ($activeCompare !== 0) {
                    return $activeCompare;
                }

                $leftSequence = self::contractSequenceTimestamp($left);
                $rightSequence = self::contractSequenceTimestamp($right);

                return ($left['is_active'] ?? false)
                    ? $leftSequence <=> $rightSequence
                    : $rightSequence <=> $leftSequence;
            })
            ->values()
            ->all();
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
        $activeContract = self::currentContract($statusData);
        $programData = self::programDataForContract($programData, $activeContract);

        $teilnehmerId = self::firstFilled([
            data_get($activeContract, 'teilnehmer_id'),
            data_get($programData, 'teilnehmer_id'),
            $activeContract ? null : data_get($statusData, 'teilnehmer_id'),
            $activeContract ? null : $person->teilnehmer_id,
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
        $query->where($pivotAlias.'.person_id', $person->id);

        $identifiers = self::identifiersFor($person);
        if (! self::hasCurrentContractFilter($identifiers)) {
            return;
        }

        $query->where(function ($current) use ($identifiers, $pivotAlias, $courseTable) {
            $hasParticipantId = ! empty($identifiers['teilnehmer_id']);
            $hasProgramFallback = ! empty($identifiers['tn_baustein_ids']) || ! empty($identifiers['klassen_ids']);

            if ($hasParticipantId) {
                $current->where($pivotAlias.'.teilnehmer_id', $identifiers['teilnehmer_id']);
            }

            if (! $hasProgramFallback) {
                return;
            }

            $legacyProgramMatch = function ($legacy) use ($identifiers, $pivotAlias, $courseTable, $hasParticipantId) {
                if ($hasParticipantId) {
                    $legacy->where(function ($unknownParticipantId) use ($pivotAlias) {
                        $unknownParticipantId
                            ->whereNull($pivotAlias.'.teilnehmer_id')
                            ->orWhere($pivotAlias.'.teilnehmer_id', '');
                    });
                }

                $legacy->where(function ($programMatch) use ($identifiers, $pivotAlias, $courseTable) {
                    $added = false;

                    if (! empty($identifiers['tn_baustein_ids'])) {
                        $programMatch->whereIn($pivotAlias.'.tn_baustein_id', $identifiers['tn_baustein_ids']);
                        $added = true;
                    }

                    if (! empty($identifiers['klassen_ids'])) {
                        if ($added) {
                            $programMatch->orWhereIn($pivotAlias.'.klassen_id', $identifiers['klassen_ids']);
                        } else {
                            $programMatch->whereIn($pivotAlias.'.klassen_id', $identifiers['klassen_ids']);
                            $added = true;
                        }

                        if ($courseTable) {
                            $programMatch->orWhereIn($courseTable.'.klassen_id', $identifiers['klassen_ids']);
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

    protected static function currentContract(array $statusData): ?array
    {
        return self::openContracts($statusData)
            ->sort(function (array $left, array $right) {
                $leftSelected = self::isSelectedContract($left);
                $rightSelected = self::isSelectedContract($right);

                if ($leftSelected !== $rightSelected) {
                    return $leftSelected ? -1 : 1;
                }

                $leftSequence = self::contractSequenceTimestamp($left);
                $rightSequence = self::contractSequenceTimestamp($right);

                if ($leftSequence !== $rightSequence) {
                    return $leftSequence <=> $rightSequence;
                }

                $leftEnd = self::effectiveContractEnd($left)?->timestamp ?? PHP_INT_MAX;
                $rightEnd = self::effectiveContractEnd($right)?->timestamp ?? PHP_INT_MAX;

                if ($leftEnd !== $rightEnd) {
                    return $leftEnd <=> $rightEnd;
                }

                return strcmp(
                    self::firstFilled([$left['teilnehmer_id'] ?? null, $left['teilnehmer_nr'] ?? null]) ?? '',
                    self::firstFilled([$right['teilnehmer_id'] ?? null, $right['teilnehmer_nr'] ?? null]) ?? '',
                );
            })
            ->first();
    }

    protected static function openContracts(array $statusData)
    {
        $today = Carbon::today('Europe/Berlin');

        return collect(data_get($statusData, 'vertraege', []))
            ->filter(fn ($contract) => is_array($contract))
            ->filter(function (array $contract) use ($today) {
                if (
                    ! self::isSelectedContract($contract)
                    && ! filter_var($contract['is_active'] ?? false, FILTER_VALIDATE_BOOL)
                ) {
                    return false;
                }

                $effectiveEnd = self::effectiveContractEnd($contract);
                if ($effectiveEnd && $effectiveEnd->endOfDay()->lt($today)) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    protected static function isSelectedContract(array $contract): bool
    {
        return filter_var($contract['is_current'] ?? false, FILTER_VALIDATE_BOOL)
            || filter_var($contract['is_selected'] ?? false, FILTER_VALIDATE_BOOL);
    }

    protected static function sameContract(array $left, ?array $right): bool
    {
        if (! $right) {
            return false;
        }

        foreach (['beratung_id', 'teilnehmer_id', 'teilnehmer_nr'] as $identifier) {
            $leftValue = self::firstFilled([$left[$identifier] ?? null]);
            $rightValue = self::firstFilled([$right[$identifier] ?? null]);

            if ($leftValue !== null && $rightValue !== null) {
                return $leftValue === $rightValue;
            }
        }

        return false;
    }

    protected static function contractSequenceTimestamp(array $contract): int
    {
        $contractStart = self::parseDate(
            self::firstFilled([
                $contract['vertrag_beginn'] ?? null,
                $contract['beginn'] ?? null,
            ]),
        );

        return $contractStart?->timestamp
            ?? self::effectiveContractEnd($contract)?->timestamp
            ?? PHP_INT_MAX;
    }

    protected static function effectiveContractEnd(array $contract): ?Carbon
    {
        $contractEnd = self::parseDate($contract['vertrag_ende'] ?? null)
            ?? self::parseDate($contract['letzter_tag'] ?? null);
        $cancelledAt = self::parseDate($contract['kuendig_zum'] ?? null);

        if ($contractEnd && $cancelledAt) {
            return $contractEnd->lte($cancelledAt) ? $contractEnd : $cancelledAt;
        }

        return $contractEnd ?? $cancelledAt;
    }

    protected static function programDataForContract(array $programData, ?array $contract): array
    {
        if (empty($programData) || ! $contract) {
            return $programData;
        }

        foreach (['teilnehmer_id', 'teilnehmer_nr'] as $identifier) {
            $contractIdentifier = self::firstFilled([$contract[$identifier] ?? null]);
            $programIdentifier = self::firstFilled([$programData[$identifier] ?? null]);

            if (
                $contractIdentifier !== null
                && $programIdentifier !== null
                && $contractIdentifier !== $programIdentifier
            ) {
                return [];
            }
        }

        return $programData;
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
