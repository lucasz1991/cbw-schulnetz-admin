<?php

namespace App\Jobs\ApiUpdates;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PersonApiUpdate implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int,int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public int $personPk)
    {
        $this->personPk = $personPk;
    }

    public function uniqueId(): string
    {
        return 'person-api-update:' . (string) $this->personPk;
    }

    public function handle(): void
    {
        // Verarbeitung erfolgt zentral in der Base-Installation ueber denselben Queue-Job-Namen.
    }
}
