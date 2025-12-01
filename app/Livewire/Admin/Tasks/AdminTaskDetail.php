<?php

namespace App\Livewire\Admin\Tasks;

use Livewire\Component;
use App\Models\AdminTask;
use Illuminate\Support\Facades\Auth;

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
        // 1) Payload einlesen
        if (is_int($payload)) {
            $taskId = $payload;
            $meta   = [];
        } else {
            $taskId = $payload['taskId'] ?? null;
            $meta   = $payload;
        }

        if (!$taskId) {
            return;
        }

        $this->taskId = $taskId;

        // 2) Task + Kontext laden
        $this->task = AdminTask::with([
            'creator',
            'assignedAdmin',
            'context'
        ])->find($taskId);

        $this->payload = $meta;

        // 3) Auf Ansichten-Start zurücksetzen
        $this->viewMode = 'task';

        // 4) Modal öffnen
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
        // nur wenn Kontext existiert
        if ($this->task && $this->task->context) {
            $this->viewMode = 'context';
        }
    }

    public function assignToMe(): void
    {
        if (!$this->taskId) {
            return;
        }

        // Task erneut laden
        $task = AdminTask::findOrFail($this->taskId);

        // Nur übernehmen, wenn noch niemand zugewiesen ist
        if (!is_null($task->assigned_to)) {
            return;
        }

        $task->assignTo(Auth::id());

        // Modal-Task aktualisieren
        $this->task = $task->fresh(['creator', 'assignedAdmin', 'context']);

        $this->dispatch('showAlert', [
            'type' => 'success',
            'title' => 'Übernommen',
            'text' => 'Aufgabe erfolgreich übernommen.'
        ]);
    }

    public function markAsCompleted(): void
    {
        if (!$this->taskId) {
            return;
        }

        $task = AdminTask::findOrFail($this->taskId);

        // Nur der aktuell zugewiesene Admin darf abschließen
        if ((int) $task->assigned_to !== (int) Auth::id()) {
            return;
        }

        $task->complete();

        // Task aktualisieren bevor wir das Modal schließen
        $this->task = $task->fresh(['creator', 'assignedAdmin', 'context']);

        // Event für Listen-Refresh
        $this->dispatch('taskCompleted');

        // Modal schließen
        $this->close();

        // Meldung zeigen
        $this->dispatch('showAlert', [
            'type' => 'success',
            'title' => 'Abgeschlossen',
            'text'  => 'Aufgabe erfolgreich abgeschlossen.'
        ]);
    }

    public function render()
    {
        return view('livewire.admin.tasks.admin-task-detail');
    }
}
