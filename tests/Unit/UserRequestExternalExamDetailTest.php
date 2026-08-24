<?php

namespace Tests\Unit;

use App\Models\AdminTask;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserRequestExternalExamDetailTest extends TestCase
{
    public function test_external_makeup_uses_current_fields_and_a_readable_reason(): void
    {
        $request = new UserRequest([
            'type' => UserRequest::TYPE_EXTERNAL_MAKEUP,
            'class_label' => 'TEST26',
            'institute' => 'Microsoft',
            'certification_label' => 'Microsoft AZ-104',
            'scheduled_at' => '2026-09-10 09:30:00',
            'fee_cents' => 20500,
            'reason' => UserRequest::REASON_CERTIFICATION_FAILED,
        ]);

        $this->assertSame('Anmeldung externe Prüfung', $request->type_label);
        $this->assertSame('Microsoft', $request->external_exam_institution);
        $this->assertSame('Microsoft AZ-104', $request->external_exam_name);
        $this->assertSame('205,00 €', $request->external_exam_fee_formatted);
        $this->assertSame('Ursprüngliche Prüfung nicht bestanden', $request->reason_label);
    }

    public function test_external_makeup_renders_the_complete_admin_detail(): void
    {
        $user = new User([
            'name' => 'Test Teilnehmer',
            'email' => 'teilnehmer@example.test',
        ]);
        $user->setRelation('person', null);

        $request = new UserRequest([
            'type' => UserRequest::TYPE_EXTERNAL_MAKEUP,
            'class_label' => 'TEST26',
            'institute' => 'Microsoft',
            'certification_label' => 'Microsoft AZ-104',
            'scheduled_at' => '2026-09-10 09:30:00',
            'fee_cents' => 20500,
            'reason' => UserRequest::LEGACY_REASON_CERTIFICATION_FAILED,
        ]);
        $request->created_at = Carbon::parse('2026-08-23 10:00:00');
        $request->setRelation('user', $user);
        $request->setRelation('files', new EloquentCollection);

        $task = new AdminTask;
        $task->setRelation('context', $request);

        $html = view('livewire.admin.tasks.partials._user-request-context', [
            'task' => $task,
        ])->render();

        $this->assertStringContainsString('Anmeldung externe Prüfung', $html);
        $this->assertStringContainsString('TEST26', $html);
        $this->assertStringContainsString('Microsoft', $html);
        $this->assertStringContainsString('Microsoft AZ-104', $html);
        $this->assertStringContainsString('10.09.2026 09:30 Uhr', $html);
        $this->assertStringContainsString('205,00 €', $html);
        $this->assertStringContainsString('Ursprüngliche Prüfung nicht bestanden', $html);
        $this->assertStringNotContainsString('keine Detailansicht definiert', $html);
        $this->assertStringNotContainsString('zert_faild', $html);
    }
}
