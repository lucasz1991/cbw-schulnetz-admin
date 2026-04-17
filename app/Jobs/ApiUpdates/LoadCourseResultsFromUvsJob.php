<?php

namespace App\Jobs\ApiUpdates;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LoadCourseResultsFromUvsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int,int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public int $coursePk)
    {
    }

    public function uniqueId(): string
    {
        return 'load-course-results-from-uvs:' . (string) $this->coursePk;
    }

    public function handle(): void
    {
        // Verarbeitung erfolgt zentral in der Base-Installation ueber denselben Queue-Job-Namen.
    }
}
