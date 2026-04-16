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

    public function __construct(
        public int $userPk,
        public ?int $personPk = null,
    ) {
    }

    public function uniqueId(): string
    {
        return 'user-api-update:' . $this->userPk . ':' . ($this->personPk !== null ? $this->personPk : 'all');
    }

    public function handle(): void
    {
        // Verarbeitung erfolgt zentral in der Base-Installation ueber denselben Queue-Job-Namen.
    }
}
