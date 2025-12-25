<?php

namespace App\Livewire\Admin\Tasks;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\AdminTask;
use Illuminate\Support\Facades\Auth;

// Falls dein Context wirklich App\Models\ReportBook ist:
use App\Models\ReportBook as ReportBookModel;

class AdminTaskDetail extends Component
{
    public ?int $taskId = null;
    public ?AdminTask $task = null;

    public bool $showDetailModal = false;

    // 'task' = Aufgabendetails, 'context' = Berichtsheft / Antrag
    public string $viewMode = 'task';

    // erhält zusätzliche Metadaten beim Öffnen
    public array $payload = [];

    protected $listeners = [
        'openAdminTaskDetail' => 'open',
    ];

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

        $this->task = AdminTask::with([
            'creator',
            'assignedAdmin',
            'context',
        ])->find($taskId);

        $this->payload = $meta;
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
        if ($this->task && $this->task->context) {
            $this->viewMode = 'context';
        }
    }

    public function assignToMe(): void
    {
        if (! $this->taskId) return;

        $task = AdminTask::findOrFail($this->taskId);

        if (! is_null($task->assigned_to)) {
            return;
        }

        $task->assignTo(Auth::id());

        $this->task = $task->fresh(['creator', 'assignedAdmin', 'context']);

        $this->dispatch('showAlert', [
            'type'  => 'success',
            'title' => 'Übernommen',
            'text'  => 'Aufgabe erfolgreich übernommen.',
        ]);
    }

    public function markAsCompleted(): void
    {
        if (! $this->taskId) return;

        $task = AdminTask::findOrFail($this->taskId);

        if ((int) $task->assigned_to !== (int) Auth::id()) {
            return;
        }

        $task->complete();

        $this->task = $task->fresh(['creator', 'assignedAdmin', 'context']);

        $this->dispatch('taskCompleted');

        $this->close();

        $this->dispatch('showAlert', [
            'type'  => 'success',
            'title' => 'Abgeschlossen',
            'text'  => 'Aufgabe erfolgreich abgeschlossen.',
        ]);
    }

    /* ============================================================
     *  ReportBook-Kontext: Ausbilder-Prüfung + Signatur
     * ============================================================ */

    /**
     * Entry-Status, den "geprüft" bedeutet.
     * (bei dir: 2)
     */
    protected int $reviewedStatus = 2;

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
        if (! $this->taskId || ! $this->task) return;

        if ($this->task->task_type !== 'reportbook_review') return;

        if ((int) $this->task->assigned_to !== (int) Auth::id()) return;

        $reportBook = $this->task->context;

        if (! $reportBook) return;

        // Signatur vorhanden?
        if (! $this->hasTrainerSignature($reportBook)) {
            $this->openTrainerSignatureForm($reportBook);

            // direkt Kontextansicht zeigen
            $this->viewMode = 'context';
            return;
        }

        // Wenn Signatur schon existiert -> direkt freigeben
        $this->applyReportBookReview($reportBook);

        $this->task = $this->task->fresh(['creator', 'assignedAdmin', 'context']);
        $this->viewMode = 'context';

        $this->markAsCompleted();
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

        if (! $this->taskId || ! $this->task) return;

        // sicherstellen: Task passt + assigned user passt
        if ($this->task->task_type !== 'reportbook_review') return;
        if ((int) $this->task->assigned_to !== (int) Auth::id()) return;

        $reportBook = $this->task->context;

        if (! $reportBook || (int) $reportBook->id !== (int) $fileableId) {
            return;
        }

        // Jetzt freigeben
        $this->applyReportBookReview($reportBook);

        $this->task = $this->task->fresh(['creator', 'assignedAdmin', 'context']);
        $this->viewMode = 'context';

        // Task abschließen
        $this->markAsCompleted();
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
        $reportBook->entries()
            ->where('status', '!=', $this->reviewedStatus)
            ->update(['status' => $this->reviewedStatus]);

        // Optional: Gesamtstatus
        if ($reportBook->isFillable('status')) {
            $reportBook->status = 'reviewed';
            $reportBook->save();
        }
    }

    /**
     * Prüft, ob Ausbilder-Signatur vorhanden ist
     */
    protected function hasTrainerSignature($reportBook): bool
    {
        // Falls du helper-Methoden hast, kannst du das hier bevorzugen:
        if (method_exists($reportBook, 'trainerSignatureFile')) {
            return (bool) $reportBook->trainerSignatureFile();
        }

        // Fallback: files()-Relation wie im Teilnehmer-Flow :contentReference[oaicite:4]{index=4}
        if (method_exists($reportBook, 'files')) {
            return $reportBook->files()
                ->where('type', $this->trainerSignatureType)
                ->exists();
        }

        return false;
    }

    public function render()
    {
        return view('livewire.admin.tasks.admin-task-detail');
    }
}
