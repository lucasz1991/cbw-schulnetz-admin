<?php

namespace App\Jobs\ApiUpdates;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UserApiUpdate implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /** @var array<int,int> */
    public array $backoff = [10, 60];

    /**
     * Base processes this job and releases unique locks only in its own cache.
     * Without an expiry, the lock taken here made the profile button
     * "Aktualisieren" a silent no-op after its first use for a person.
     */
    public int $uniqueFor = 900;

    public function __construct(
        public int $userPk,
        public ?int $personPk = null,
    ) {
    }

    public function uniqueId(): string
    {
        // Own key space: locks taken before the expiry existed never end.
        return 'admin-user-api-update:' . $this->userPk . ':' . ($this->personPk !== null ? $this->personPk : 'all');
    }

    public function handle(): void
    {
        // Verarbeitung erfolgt zentral in der Base-Installation ueber denselben Queue-Job-Namen.
    }
}
