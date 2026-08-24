<?php

namespace App\Livewire\Admin\Tasks;

use App\Actions\AdminTasks\AssignAdminTask;
use App\Actions\AdminTasks\CompleteReportBookReview;
use Livewire\Component;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use App\Models\AdminTask;
use App\Models\Mail;
use App\Models\Setting;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

// Falls dein Context wirklich App\Models\ReportBook ist:
use App\Models\ReportBook as ReportBookModel;

class AdminTaskDetail extends Component
{
    #[Locked]
    public ?int $taskId = null;

    #[Locked]
    public ?int $loadedAssignedTo = null;

    public ?AdminTask $task = null;

    public bool $showDetailModal = false;

    // 'task' = Aufgabendetails, 'context' = Berichtsheft / Antrag
    public string $viewMode = 'task';

    // erhält zusätzliche Metadaten beim Öffnen
    public array $payload = [];
    public array $entryApprovals = [];
    public string $rejectionComment = '';

    protected $listeners = [
        'openAdminTaskDetail' => 'open',
    ];

    public function boot(): void
    {
        Gate::authorize('jobs.view');
    }

    public function open(int|array $payload): void
    {
        if (is_int($payload)) {
            $taskId = $payload;
            $meta   = [];
        } else {
            $taskId = $payload['taskId'] ?? null;
            $meta   = $payload;
        }

        if (! $taskId) return;

        $this->taskId = $taskId;

        $this->refreshTask();

        if (! $this->task) {
            return;
        }

        $this->payload = $meta;
        $this->initializeEntryApprovals();
        $this->rejectionComment = '';
        $this->viewMode = 'task';
        $this->showDetailModal = true;
    }

    public function close(): void
    {
        $this->showDetailModal = false;
    }

    public function switchToTask(): void
    {
        $this->viewMode = 'task';
    }

    public function switchToContext(): void
    {
        $task = $this->refreshTask();
        $isAssignee = $task && (int) $task->assigned_to === (int) Auth::id();
        $isAdmin = (bool) Auth::user()?->isAdmin();

        if ($task?->context && ($isAssignee || $isAdmin)) {
            $this->viewMode = 'context';
        }
    }

    public function assignToMe(): void
    {
        if (! $this->taskId) return;

        if ($this->loadedAssignedTo !== null) {
            $this->refreshTask();
            return;
        }

        $task = app(AssignAdminTask::class)->claim($this->taskId, (int) Auth::id());

        if (! $task) {
            $this->refreshTask();
            $this->dispatch(
                'swal:toast',
                type: 'warning',
                title: 'Nicht übernommen',
                text: 'Die Aufgabe wurde zwischenzeitlich geändert. Bitte prüfe die aktuelle Zuordnung.'
            );

            return;
        }

        $this->refreshTask();
        $this->viewMode = $this->task?->context ? 'context' : 'task';


        $this->dispatch('taskAssigned');

        $this->dispatch('swal:toast', type: 'success', title: 'Übernommen', text: 'Aufgabe erfolgreich übernommen.');
    }

    public function takeOver(): void
    {
        if (! $this->taskId || $this->loadedAssignedTo === null) {
            return;
        }

        $userId = (int) Auth::id();

        if ($this->loadedAssignedTo === $userId) {
            return;
        }

        $task = app(AssignAdminTask::class)->takeOver(
            $this->taskId,
            $userId,
            $this->loadedAssignedTo
        );

        if (! $task) {
            $this->refreshTask();
            $this->dispatch(
                'swal:toast',
                type: 'warning',
                title: 'Nicht übernommen',
                text: 'Die Aufgabe wurde zwischenzeitlich geändert oder ist nicht mehr aktiv.'
            );

            return;
        }

        $this->refreshTask();
        $this->viewMode = $this->task?->context ? 'context' : 'task';

        $this->dispatch('taskAssigned');

        $this->dispatch('swal:toast', type: 'success', title: 'Übernommen', text: 'Aufgabe erfolgreich übernommen.');
    }

    public function markAsCompleted(): void
    {
        if (! $this->taskId || $this->loadedAssignedTo === null) return;

        $task = app(AssignAdminTask::class)->complete(
            $this->taskId,
            (int) Auth::id(),
            $this->loadedAssignedTo
        );

        if (! $task) {
            $this->refreshTask();
            $this->dispatch(
                'swal:toast',
                type: 'warning',
                title: 'Nicht abgeschlossen',
                text: 'Die Aufgabe wurde zwischenzeitlich geändert oder von jemand anderem übernommen.'
            );

            return;
        }

        $this->refreshTask();

        $this->dispatch('taskCompleted');

        $this->close();

        $this->dispatch('swal:toast', type: 'success', title: 'Abgeschlossen', text: 'Aufgabe erfolgreich abgeschlossen.');
    }

    public function releaseTask(): void
    {
        if (! $this->taskId || $this->loadedAssignedTo === null) return;

        $task = app(AssignAdminTask::class)->release(
            $this->taskId,
            (int) Auth::id(),
            $this->loadedAssignedTo,
            (bool) Auth::user()?->isAdmin()
        );

        if (! $task) {
            $this->refreshTask();
            $this->dispatch(
                'swal:toast',
                type: 'warning',
                title: 'Nicht zurückgegeben',
                text: 'Die Aufgabe wurde zwischenzeitlich geändert oder von jemand anderem übernommen.'
            );

            return;
        }

        $this->viewMode = 'task';
        $this->refreshTask();

        // Existing listener in list uses this event to refresh.
        $this->dispatch('taskAssigned');

        $this->dispatch('swal:toast', type: 'success', title: 'Zurückgegeben', text: 'Aufgabe wurde wieder freigegeben.');
    }

    /* ============================================================
     *  ReportBook-Kontext: Ausbilder-Prüfung + Signatur
     * ============================================================ */

    /**
     * Entry-Status, den "geprüft" bedeutet.
     * (bei dir: 2)
     */
    protected int $reviewedStatus = 2;
    protected int $rejectedStatus = 3;

    /**
     * File-Type für Ausbilder-Signatur (wie Teilnehmer: sign_reportbook_participant)
     */
    protected string $trainerSignatureType = 'sign_reportbook_trainer';

    /**
     * Startet die Freigabe:
     * - prüft Task + Rechte
     * - prüft, ob Ausbilder-Signatur existiert
     * - wenn nicht: öffnet Signature-Form
     * - wenn ja: führt Approval aus + Task abschließen
     */
    public function approveReportBook(): void
    {
        if (! $this->taskId) return;

        $task = $this->refreshTask();

        if (! $task) return;

        if ($task->task_type !== AdminTask::TYPE_REPORTBOOK_REVIEW) return;

        if ((int) $task->assigned_to !== (int) Auth::id()) return;

        $reportBook = $task->context;

        if (! $reportBook) return;

        if ($this->hasRejectedEntries()) {
            $this->dispatch('swal:toast', type: 'warning', title: 'Freigabe nicht möglich', text: 'Es sind abgelehnte Einträge markiert. Nutze in diesem Fall bitte "Ablehnen".');
            return;
        }

        // Signatur vorhanden?
        if (! $this->hasTrainerSignature($reportBook)) {
            $this->openTrainerSignatureForm($reportBook);

            // direkt Kontextansicht zeigen
            $this->viewMode = 'context';
            return;
        }

        // Wenn Signatur schon existiert -> direkt freigeben
        $this->completeReportBookReviewAtomically(
            (int) $reportBook->id,
            function (ReportBookModel $currentReportBook): void {
                $this->applyReportBookReview($currentReportBook);
                $this->sendApprovalMessage($currentReportBook);
            }
        );
    }

    public function rejectReportBook(): void
    {
        if (! $this->taskId) return;

        $task = $this->refreshTask();

        if (! $task) return;
        if ($task->task_type !== AdminTask::TYPE_REPORTBOOK_REVIEW) return;
        if ((int) $task->assigned_to !== (int) Auth::id()) return;

        $reportBook = $task->context;

        if (! $reportBook) return;

        if (! $this->hasRejectedEntries()) {
            return;
        }


        $completed = $this->completeReportBookReviewAtomically(
            (int) $reportBook->id,
            function (ReportBookModel $currentReportBook): void {
                $this->applyReportBookReview($currentReportBook);
                $this->sendReportBookRejectionMessage($currentReportBook);
            }
        );

        if ($completed) {
            $this->rejectionComment = '';
        }
    }

    /**
     * Öffnet das generische Signature-Modal (wie im Teilnehmer-Flow) :contentReference[oaicite:3]{index=3}
     */
    protected function openTrainerSignatureForm($reportBook): void
    {
        // Kontext-Name für den Dialog
        $courseTitle = data_get($reportBook, 'course.title') ?? 'Kurs';
        $klasse      = data_get($reportBook, 'course.klassen_id');

        $contextName = $klasse ? "{$courseTitle} – {$klasse}" : $courseTitle;

        $this->dispatch('openSignatureForm', [
            'fileableType' => ReportBookModel::class,
            'fileableId'   => (int) $reportBook->id,
            'fileType'     => 'sign_reportbook_trainer',
            'label'        => 'Berichtsheft prüfen',
            'signForName'  => 'Berichtsheft (Ausbilder)',
            'contextName'  => $contextName,
            'confirmText'  => "Ich bestätige als <strong>Ausbilder</strong>, dass ich das Berichtsheft<br><strong>({$contextName})</strong><br>geprüft habe und die Angaben vollständig sind.",
        ]);
    }

    /**
     * Wird ausgelöst, wenn das Signature-Modul fertig ist.
     * Danach: Approval + Task abschließen.
     */
    #[On('signatureCompleted')]
    public function handleTrainerSignatureCompleted(array $payload): void
    {
        $fileableType = data_get($payload, 'fileableType');
        $fileType     = data_get($payload, 'fileType');
        $fileableId   = (int) data_get($payload, 'fileableId');

        if (
            $fileableType !== ReportBookModel::class ||
            $fileType !== $this->trainerSignatureType ||
            ! $fileableId
        ) {
            return;
        }

        if (! $this->taskId) return;

        $task = $this->refreshTask();

        if (! $task) return;

        // sicherstellen: Task passt + assigned user passt
        if ($task->task_type !== AdminTask::TYPE_REPORTBOOK_REVIEW) return;
        if ((int) $task->assigned_to !== (int) Auth::id()) return;

        $reportBook = $task->context;

        if (! $reportBook || (int) $reportBook->id !== (int) $fileableId) {
            return;
        }

        if ($this->hasRejectedEntries()) {
            $this->dispatch('swal:toast', type: 'warning', title: 'Freigabe nicht möglich', text: 'Es sind abgelehnte Einträge markiert. Nutze in diesem Fall bitte "Ablehnen".');
            return;
        }

        // Jetzt freigeben
        $this->completeReportBookReviewAtomically(
            (int) $reportBook->id,
            function (ReportBookModel $currentReportBook): void {
                $this->applyReportBookReview($currentReportBook);
                $this->sendApprovalMessage($currentReportBook);
            }
        );
    }

    #[On('signatureAborted')]
    public function handleTrainerSignatureAborted(array $payload = []): void
    {
        // optional: Info/Toast
        return;
    }

    /**
     * Setzt alle Einträge auf reviewedStatus (=2) und optional den Gesamtstatus.
     */
    protected function applyReportBookReview($reportBook): void
    {
        $entryIds = $reportBook->entries()->pluck('id')->map(fn ($id) => (string) $id);

        $approvedEntryIds = [];
        $rejectedEntryIds = [];

        foreach ($entryIds as $entryId) {
            $isApproved = filter_var(
                $this->entryApprovals[$entryId] ?? true,
                FILTER_VALIDATE_BOOLEAN
            );

            if ($isApproved) {
                $approvedEntryIds[] = (int) $entryId;
                continue;
            }

            $rejectedEntryIds[] = (int) $entryId;
        }

        if (! empty($approvedEntryIds)) {
            $reportBook->entries()
                ->whereIn('id', $approvedEntryIds)
                ->update(['status' => $this->reviewedStatus]);
        }

        if (! empty($rejectedEntryIds)) {
            $reportBook->entries()
                ->whereIn('id', $rejectedEntryIds)
                ->update(['status' => $this->rejectedStatus]);
        }

    }

    protected function completeReportBookReviewAtomically(int $reportBookId, Closure $review): bool
    {
        $task = app(CompleteReportBookReview::class)->handle(
            (int) $this->taskId,
            (int) Auth::id(),
            $reportBookId,
            $review
        );

        if (! $task) {
            $this->refreshTask();
            $this->dispatch(
                'swal:toast',
                type: 'warning',
                title: 'Prüfung nicht gespeichert',
                text: 'Die Aufgabe wurde zwischenzeitlich geändert oder von jemand anderem übernommen.'
            );

            return false;
        }

        $this->refreshTask();
        $this->viewMode = 'context';
        $this->dispatch('taskCompleted');
        $this->close();
        $this->dispatch('swal:toast', type: 'success', title: 'Abgeschlossen', text: 'Aufgabe erfolgreich abgeschlossen.');

        return true;
    }


    protected function initializeEntryApprovals(): void
    {
        $this->entryApprovals = [];

        if (! $this->task) {
            return;
        }

        if ($this->task->task_type !== 'reportbook_review' || ! $this->task->context) {
            return;
        }

        $entryIds = $this->task->context->entries()->pluck('id');

        foreach ($entryIds as $entryId) {
            $this->entryApprovals[(string) $entryId] = true;
        }
    }

    protected function hasRejectedEntries(): bool
    {
        foreach ($this->entryApprovals as $isApproved) {
            $approved = filter_var($isApproved, FILTER_VALIDATE_BOOLEAN);

            if (! $approved) {
                return true;
            }
        }

        return false;
    }

    protected function sendReportBookRejectionMessage($reportBook): void
    {
        $recipientId = (int) data_get($reportBook, 'user_id');

        if (! $recipientId) {
            return;
        }

        $courseTitle = data_get($reportBook, 'course.title') ?? 'dein Berichtsheft';
        $courseId = (int) data_get($reportBook, 'course_id');
        $baseUrl = rtrim((string) (Setting::getValue('api', 'base_api_url') ?: config('app.url')), '/');
        $rejectedEntryIds = $this->getRejectedEntryIds();

        $rejectedEntries = $reportBook->entries()
            ->whereIn('id', $rejectedEntryIds)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get(['entry_date', 'created_at', 'course_day_id']);

        $entryLines = [];
        foreach ($rejectedEntries as $entry) {
            $dateValue = $entry->entry_date ?? $entry->created_at;
            $dateText = $dateValue ? $dateValue->format('d.m.Y') : '-';
            $dayId = (int) ($entry->course_day_id ?? 0);

            if ($courseId > 0 && $dayId > 0) {
                $entryLines[] = "- {$dateText}: <a href='{$baseUrl}/user/reportbook?course={$courseId}&day={$dayId}' target='_blank'>Berichtsheft ansehen</a><br>";
                continue;
            }

            if ($courseId > 0) {
                $entryLines[] = "- {$dateText}: <a href='{$baseUrl}/user/reportbook?course={$courseId}' target='_blank'>Berichtsheft ansehen</a>";
                continue;
            }

            $entryLines[] = "- {$dateText}";
        }

        $entriesText = empty($entryLines)
            ? "- Keine konkreten Einträge gefunden."
            : implode("\n", $entryLines);

        $subject = 'Berichtsheft abgelehnt';
        $body = "Dein Berichtsheft für den Baustein ({$courseTitle}) wurde abgelehnt und muss neu eingereicht werden.<br><br>"
            . "Abgelehnte Einträge:<br>"
            . "{$entriesText}<br><br>"
            . "Begründung:<br>"
            . trim($this->rejectionComment);

        Mail::create([
            'type' => 'both',
            'status' => false,
            'content' => [
                'subject' => $subject,
                'header'  => 'Berichtsheft abgelehnt',
                'body'    => $body,
                'link'    => '',
            ],
            'recipients' => [[
                'user_id' => $recipientId,
                'email'   => (string) data_get($reportBook, 'user.email', ''),
                'status'  => false,
            ]],
        ]);
    }

    protected function sendApprovalMessage($reportBook): void
    {
        $recipientId = (int) data_get($reportBook, 'user_id');

        if (! $recipientId) {
            return;
        }

        $courseTitle = data_get($reportBook, 'course.title') ?? 'dein Berichtsheft';
        $baseUrl = rtrim((string) (Setting::getValue('api', 'base_api_url') ?: config('app.url')), '/');
        $courseId = (int) data_get($reportBook, 'course_id');

        $subject = 'Berichtsheft freigegeben';
        $body = "Dein Berichtsheft für den Baustein ({$courseTitle}) wurde freigegeben. Gute Arbeit!";
        $link = "{$baseUrl}/user/reportbook?course={$courseId}"; 

        Mail::create([
            'type' => 'both',
            'status' => false,
            'content' => [
                'subject' => $subject,
                'header'  => 'Berichtsheft freigegeben',
                'body'    => $body,
                'link'    => $link,
            ],
            'recipients' => [[
                'user_id' => $recipientId,
                'email'   => (string) data_get($reportBook, 'user.email', ''),
                'status'  => false,
            ]],
        ]);
    }

    protected function getRejectedEntryIds(): array
    {
        $ids = [];

        foreach ($this->entryApprovals as $entryId => $isApproved) {
            $approved = filter_var($isApproved, FILTER_VALIDATE_BOOLEAN);

            if (! $approved) {
                $ids[] = (int) $entryId;
            }
        }

        return $ids;
    }

    /**
     * Prüft, ob Ausbilder-Signatur vorhanden ist
     */
    protected function hasTrainerSignature($reportBook): bool
    {
        if (method_exists($reportBook, 'trainerSignatureFile')) {
            return (bool) $reportBook->trainerSignatureFile();
        }

        if (method_exists($reportBook, 'files')) {
            return $reportBook->files()
                ->where('type', $this->trainerSignatureType)
                ->exists();
        }

        return false;
    }

    protected function refreshTask(): ?AdminTask
    {
        if (! $this->taskId) {
            $this->task = null;
            $this->loadedAssignedTo = null;

            return null;
        }

        $this->task = AdminTask::with([
            'creator',
            'assignedAdmin',
            'context',
        ])->find($this->taskId);

        $this->loadedAssignedTo = $this->task?->assigned_to === null
            ? null
            : (int) $this->task->assigned_to;

        return $this->task;
    }

    public function render()
    {
        return view('livewire.admin.tasks.admin-task-detail');
    }
}
