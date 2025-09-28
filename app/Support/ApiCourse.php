<?php

namespace App\Support;

use Illuminate\Support\Fluent;
use Illuminate\Support\Carbon;

class ApiCourse extends Fluent
{
    public function __construct(array $attributes = [])
    {
        // Normalisieren
        $attributes = [
            'id'                  => $attributes['klassen_id']        ?? null,
            'short'               => $attributes['kurzbez']           ?? null,
            'title'               => $attributes['bezeichnung']       ?? ($attributes['kurzbez'] ?? null),
            'start_raw'           => $attributes['beginn']            ?? null,
            'end_raw'             => $attributes['ende']              ?? null,
            'participants_count'  => (int)($attributes['participants_count'] ?? 0),
            'teachers_count'      => (int)($attributes['teachers_count'] ?? 0),
        ];

        // Datums-Parsen (API liefert z. B. Y/m/d)
        $attributes['start_time'] = static::toCarbon($attributes['start_raw']);
        $attributes['end_time']   = static::toCarbon($attributes['end_raw']);

        parent::__construct($attributes);
    }

    protected static function toCarbon(?string $val): ?Carbon
    {
        if (!$val) return null;

        // Versuche gängige Formate der UVS-API
        $formats = ['Y/m/d H:i:s', 'Y/m/d', 'Y-m-d H:i:s', 'Y-m-d'];
        foreach ($formats as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $val);
            } catch (\Throwable) {}
        }
        // Fallback
        try {
            return Carbon::parse($val);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getStatusAttribute(): string
    {
        $now = now();
        $start = $this->start_time;
        $end   = $this->end_time;

        if ($start && $end) {
            if ($now->lt($start)) return 'scheduled';
            if ($now->between($start, $end)) return 'active';
            if ($now->gt($end)) return 'completed';
        } elseif ($start && !$end) {
            return $now->lt($start) ? 'scheduled' : 'active';
        }

        return 'unknown';
    }
}
