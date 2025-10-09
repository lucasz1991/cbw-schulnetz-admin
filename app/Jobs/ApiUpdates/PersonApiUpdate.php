<?php

namespace App\Jobs\ApiUpdates;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PersonApiUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $personPk) 
    {
        $this->personPk = $personPk;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
    }
}
