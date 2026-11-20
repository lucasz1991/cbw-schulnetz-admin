<?php

namespace App\Jobs\ApiUpdates;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class PersonApiUpdate implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int,int> */
    public array $backoff = [10, 60, 180];

    /**
     * Base processes this job and releases unique locks only in its own cache.
     * Without an expiry, the lock taken here blocked every later update of the
     * person forever (the dispatch was silently skipped).
     */
    public int $uniqueFor = 900;

    public bool $withoutCooldown = false;

    public ?string $manualRequestId = null;

    public function __construct(public int $personPk, bool $withoutCooldown = false)
    {
        $this->personPk = $personPk;
        $this->withoutCooldown = $withoutCooldown;
        $this->manualRequestId = $withoutCooldown ? (string) Str::uuid() : null;
    }

    public function uniqueId(): string
    {
        $manualSuffix = $this->withoutCooldown
            ? ':manual:' . ($this->manualRequestId ?? 'legacy')
            : '';

        // Own key space: locks taken before the expiry existed never end.
        return 'admin-person-api-update:' . (string) $this->personPk . $manualSuffix;
    }

    public function handle(): void
    {
        // Verarbeitung erfolgt zentral in der Base-Installation ueber denselben Queue-Job-Namen.
    }
}
