<?php

namespace Tests\Feature;

use App\Livewire\AdminConfig;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CourseRatingsMailSettingsTest extends TestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array',
                'session.driver' => 'array',
                'mail.default' => 'array',
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
        });
        $user = new User();
        $user->forceFill(['id' => 1, 'role' => 'admin']);
        $this->actingAs($user);
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'Europe/Berlin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function settingsComponent(): AdminConfig
    {
        $component = new AdminConfig();
        $component->mount();

        return $component;
    }

    public function test_missing_settings_default_to_disabled_without_creating_settings(): void
    {
        $component = $this->settingsComponent();
        $this->assertFalse($component->courseRatingsMailEnabled);
        $this->assertSame('', $component->courseRatingsMailRecipients);
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_activating_saves_the_whole_normalized_list_and_berlin_start_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 23:30:00', 'UTC'));
        Cache::put('settings.mails.course_ratings_mail_enabled', false);
        $component = $this->settingsComponent();
        $component->courseRatingsMailEnabled = true;
        $component->courseRatingsMailRecipients = ' one@example.de, two@example.de, one@example.de ';
        $component->saveCourseRatingsMailSettings();

        $this->assertTrue(Setting::getValue('mails', 'course_ratings_mail_enabled'));
        $this->assertSame('one@example.de, two@example.de', Setting::getValueUncached('mails', 'course_ratings_mail_recipients'));
        $this->assertSame('2026-10-06', Setting::getValueUncached('mails', 'course_ratings_mail_start_date'));
        $this->assertSame('one@example.de, two@example.de', $component->courseRatingsMailRecipients);
        $reloaded = $this->settingsComponent();
        $this->assertTrue($reloaded->courseRatingsMailEnabled);
        $this->assertSame($component->courseRatingsMailRecipients, $reloaded->courseRatingsMailRecipients);
    }

    public function test_enabled_edits_and_disabling_keep_the_start_date_but_reactivation_resets_it(): void
    {
        $component = $this->settingsComponent();
        $component->courseRatingsMailEnabled = true;
        $component->courseRatingsMailRecipients = 'one@example.de';
        $component->saveCourseRatingsMailSettings();
        Carbon::setTestNow(Carbon::parse('2026-10-07 12:00:00', 'Europe/Berlin'));
        $component->courseRatingsMailRecipients = 'two@example.de';
        $component->saveCourseRatingsMailSettings();
        $this->assertSame('2026-10-05', Setting::getValueUncached('mails', 'course_ratings_mail_start_date'));

        $component->courseRatingsMailEnabled = false;
        $component->courseRatingsMailRecipients = '';
        $component->saveCourseRatingsMailSettings();
        $this->assertFalse(Setting::getValueUncached('mails', 'course_ratings_mail_enabled'));
        $this->assertSame('', Setting::getValueUncached('mails', 'course_ratings_mail_recipients'));
        $this->assertSame('2026-10-05', Setting::getValueUncached('mails', 'course_ratings_mail_start_date'));

        $component->courseRatingsMailEnabled = true;
        $component->courseRatingsMailRecipients = 'three@example.de';
        $component->saveCourseRatingsMailSettings();
        $this->assertSame('2026-10-07', Setting::getValueUncached('mails', 'course_ratings_mail_start_date'));
    }

    public function test_invalid_or_missing_recipients_never_partially_save_settings(): void
    {
        foreach ([
            '',
            '   ',
            'one@example.de,invalid',
            'invalid,one@example.de',
            'one@example.de;two@example.de',
            'one@example.de,,two@example.de',
            "one@example.de\r\nBcc: injected@example.de",
            ['one@example.de'],
        ] as $recipients) {
            $component = $this->settingsComponent();
            $component->courseRatingsMailEnabled = true;
            $component->courseRatingsMailRecipients = $recipients;
            try {
                $component->saveCourseRatingsMailSettings();
                $this->fail('Ungültige Empfängerliste wurde gespeichert.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('courseRatingsMailRecipients', $exception->errors());
            }
            $this->assertDatabaseCount('settings', 0);
        }

        $component->courseRatingsMailEnabled = false;
        $component->courseRatingsMailRecipients = 'one@example.de,invalid';
        $this->expectException(ValidationException::class);
        $component->saveCourseRatingsMailSettings();
    }

    public function test_save_rechecks_settings_permission_after_mount(): void
    {
        $component = $this->settingsComponent();
        $user = new User();
        $user->forceFill(['id' => 2, 'role' => 'staff']);
        $this->actingAs($user);
        Gate::define('settings.manage', fn () => false);
        $component->courseRatingsMailEnabled = true;
        $component->courseRatingsMailRecipients = 'one@example.de';

        try {
            $component->saveCourseRatingsMailSettings();
            $this->fail('Speichern ohne Einstellungsrecht wurde erlaubt.');
        } catch (AuthorizationException $exception) {
            $this->assertDatabaseCount('settings', 0);
        }
    }

    public function test_settings_permission_allows_staff_to_save_the_new_mail_setting(): void
    {
        $user = new User();
        $user->forceFill(['id' => 2, 'role' => 'staff']);
        $this->actingAs($user);
        Gate::define('settings.manage', fn () => true);
        $component = $this->settingsComponent();
        $component->courseRatingsMailEnabled = true;
        $component->courseRatingsMailRecipients = 'one@example.de';
        $component->saveCourseRatingsMailSettings();

        $this->assertTrue(Setting::getValueUncached('mails', 'course_ratings_mail_enabled'));
    }

    public function test_new_mail_segment_renders_with_existing_collapse_and_accessible_controls(): void
    {
        $view = file_get_contents(resource_path('views/livewire/admin-config.blade.php'));
        $start = strrpos(substr($view, 0, strpos($view, 'Kursbewertungen E-Mail')), '<x-settings-collapse>');
        $end = strpos($view, '</x-settings-collapse>', $start) + strlen('</x-settings-collapse>');
        $html = Blade::render(substr($view, $start, $end - $start), ['errors' => new ViewErrorBag()]);

        $this->assertStringContainsString('wire:submit="saveCourseRatingsMailSettings"', $html);
        $this->assertStringContainsString('for="course_ratings_mail_enabled"', $html);
        $this->assertStringContainsString('for="course_ratings_mail_recipients"', $html);
        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('Wöchentlicher Sammelversand montags um 06:00 Uhr', $html);
        $this->assertStringContainsString('am Freitag der Vorwoche abgeschlossenen Bausteine', $html);
        $this->assertStringContainsString('Downloadlink', $html);
    }

    public function test_livewire_hydrates_the_toggle_and_recipient_list_before_submission(): void
    {
        $component = Livewire::test(CourseRatingsMailSettingsHarness::class)
            ->assertSet('courseRatingsMailEnabled', false)
            ->assertSet('courseRatingsMailRecipients', '')
            ->assertSee('Kursbewertungen E-Mail')
            ->set('courseRatingsMailEnabled', true)
            ->call('saveCourseRatingsMailSettings')
            ->assertHasErrors(['courseRatingsMailRecipients' => 'required']);
        $this->assertDatabaseCount('settings', 0);

        $component->set('courseRatingsMailRecipients', 'one@example.de, two@example.de ')
            ->call('saveCourseRatingsMailSettings')
            ->assertHasNoErrors()
            ->assertSet('courseRatingsMailRecipients', 'one@example.de, two@example.de')
            ->assertDispatched('showAlert');
        $this->assertTrue(Setting::getValueUncached('mails', 'course_ratings_mail_enabled'));

        $component->set('courseRatingsMailEnabled', false)
            ->call('saveCourseRatingsMailSettings')
            ->assertHasNoErrors();
        $this->assertFalse(Setting::getValueUncached('mails', 'course_ratings_mail_enabled'));
    }
}

class CourseRatingsMailSettingsHarness extends AdminConfig
{
    public function render()
    {
        // Render the actual new section; unrelated settings components are outside this test's scope.
        $view = file_get_contents(resource_path('views/livewire/admin-config.blade.php'));
        $start = strrpos(substr($view, 0, strpos($view, 'Kursbewertungen E-Mail')), '<x-settings-collapse>');
        $end = strpos($view, '</x-settings-collapse>', $start) + strlen('</x-settings-collapse>');

        return '<div>'.substr($view, $start, $end - $start).'</div>';
    }
}
