<?php

namespace App\Services\ApiUvs\AssetsApiServices;

use App\Models\Setting;
use App\Services\ApiUvs\ApiUvsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InstitutionsLoadService
{
    private const SETTINGS_TYPE = 'uvs_assets';
    private const SETTINGS_KEY = 'institutions_cache';
    private const TTL_HOURS = 12;

    public function __construct(
        protected ApiUvsService $apiUvsService
    ) {
    }

    public function getInstitutions(bool $forceRefresh = false): array
    {
        return array_values($this->getInstitutionsInfos($forceRefresh));
    }

    public function getInstitutionsInfos(bool $forceRefresh = false): array
    {
        $cachedPayload = Setting::getValue(self::SETTINGS_TYPE, self::SETTINGS_KEY);
        $cachedInstitutions = $this->extractInstitutions($cachedPayload);

        if (! $forceRefresh && $this->isCacheFresh($cachedPayload)) {
            return $cachedInstitutions;
        }

        $response = $this->apiUvsService->getInstitutions();

        if ($response['ok'] ?? false) {
            $institutions = $this->normalizeInstitutions(data_get($response, 'data.data', []));
            $this->storeInstitutions($institutions);

            return $institutions;
        }

        return $cachedInstitutions;
    }

    public function getInstitutionOptions(bool $forceRefresh = false): array
    {
        return collect($this->getInstitutionsInfos($forceRefresh))
            ->values()
            ->map(function (array $institution): array {
                $id = data_get($institution, 'institut_id');
                $name = trim((string) data_get($institution, 'name', ''));
                $city = trim((string) data_get($institution, 'ort', ''));
                $label = $name . ($city !== '' ? ' (' . $city . ')' : '');

                return [
                    'value' => $id === null ? '' : (string) $id,
                    'label' => $city !== '' ? $city : ('#' . $id),
                ];
            })
            ->filter(fn (array $option): bool => $option['value'] !== '')
            ->values()
            ->all();
    }

    protected function storeInstitutions(array $institutions): void
    {
        $cachedAt = now();

        Setting::setValue(self::SETTINGS_TYPE, self::SETTINGS_KEY, [
            'cached_at' => $cachedAt->toDateTimeString(),
            'valid_until' => $cachedAt->copy()->addHours(self::TTL_HOURS)->toDateTimeString(),
            'data' => $institutions,
        ]);
    }

    protected function isCacheFresh(mixed $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        $validUntil = data_get($payload, 'valid_until');

        if (! is_string($validUntil) || trim($validUntil) === '') {
            return false;
        }

        try {
            return Carbon::parse($validUntil)->isFuture();
        } catch (\Throwable) {
            return false;
        }
    }

    protected function extractInstitutions(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $data = data_get($payload, 'data', []);

        return is_array($data) ? $data : [];
    }

    protected function normalizeInstitutions(mixed $rows): array
    {
        return $this->rowsToCollection($rows)
            ->map(function (mixed $row): array {
                return is_array($row) ? $row : (array) $row;
            })
            ->filter(fn (array $row): bool => isset($row['institut_id']))
            ->sortBy([
                fn (array $row) => mb_strtolower((string) ($row['name'] ?? '')),
                fn (array $row) => (int) ($row['institut_id'] ?? 0),
            ])
            ->keyBy(fn (array $row) => (string) $row['institut_id'])
            ->all();
    }

    protected function rowsToCollection(mixed $rows): Collection
    {
        if ($rows instanceof Collection) {
            return $rows;
        }

        if (is_array($rows)) {
            return collect($rows);
        }

        if ($rows instanceof \Traversable) {
            return collect(iterator_to_array($rows));
        }

        return collect();
    }
}
