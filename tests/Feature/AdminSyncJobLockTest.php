<?php

namespace Tests\Feature;

use App\Jobs\ApiUpdates\PersonApiUpdate;
use App\Jobs\ApiUpdates\UserApiUpdate;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin only queues these jobs; Base processes them and releases unique locks in
 * its own cache. Admin locks therefore have to expire on their own.
 */
class AdminSyncJobLockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'admin_sync_job_lock_testing');
        config()->set('database.connections.admin_sync_job_lock_testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('admin_sync_job_lock_testing');
        config()->set('cache.default', 'array');
        config()->set('cache.stores.base_installation', ['driver' => 'array']);
        Bus::fake();
    }

    public function test_profile_refresh_can_be_requested_again_after_base_processed_it(): void
    {
        UserApiUpdate::dispatch(7, 11);
        $this->processInBase(new UserApiUpdate(7, 11));

        UserApiUpdate::dispatch(7, 11);
        Bus::assertDispatchedTimes(UserApiUpdate::class, 1);

        $this->travel(901)->seconds();
        UserApiUpdate::dispatch(7, 11);
        Bus::assertDispatchedTimes(UserApiUpdate::class, 2);
    }

    public function test_automatic_person_refresh_can_be_requested_again_after_base_processed_it(): void
    {
        PersonApiUpdate::dispatch(11);
        $this->processInBase(new PersonApiUpdate(11));

        PersonApiUpdate::dispatch(11);
        Bus::assertDispatchedTimes(PersonApiUpdate::class, 1);

        $this->travel(901)->seconds();
        PersonApiUpdate::dispatch(11);
        Bus::assertDispatchedTimes(PersonApiUpdate::class, 2);
    }

    public function test_locks_left_by_earlier_versions_do_not_block_updates(): void
    {
        // Earlier versions locked these keys without expiry; nothing ever released them.
        Cache::lock('laravel_unique_job:'.UserApiUpdate::class.'user-api-update:7:11')->get();
        Cache::lock('laravel_unique_job:'.PersonApiUpdate::class.'person-api-update:11')->get();

        UserApiUpdate::dispatch(7, 11);
        PersonApiUpdate::dispatch(11);

        Bus::assertDispatchedTimes(UserApiUpdate::class, 1);
        Bus::assertDispatchedTimes(PersonApiUpdate::class, 1);
    }

    /** The worker of the Base installation releases the lock in its own cache only. */
    private function processInBase(object $job): void
    {
        (new UniqueLock(Cache::store('base_installation')))->release($job);
    }
}
