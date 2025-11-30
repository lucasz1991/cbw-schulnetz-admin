<?php

namespace App\Livewire\Admin\Tasks;

use Livewire\Component;
use App\Models\AdminTask;

class AdminTaskDetail extends Component
{
    public ?int $taskId = null;
    public ?AdminTask $task = null;

    public bool $showDetailModal = false;

    protected $listeners = [
        'openAdminTaskDetail' => 'open',
    ];

    public function open(int|array $payload): void
    {
        // 1) Payload normalisieren
        if (is_int($payload)) {
            $taskId = $payload;
            $meta   = [];
        } else {
            $taskId = $payload['taskId'] ?? null;
            $meta   = $payload; // gesamte Payload behalten
        }

        if (!$taskId) {
            return; // oder Exception, falls erwünscht
        }

        // 2) Task laden
        $this->taskId = $taskId;
        $this->task   = AdminTask::with(['creator', 'assignedAdmin'])->find($taskId);

        // 3) Optional: Payload-Metadaten speichern (falls du später nutzen willst)
        $this->payload = $meta; // → einfach als public property hinzufügen, wenn gebraucht

        // 4) Modal öffnen
        $this->showDetailModal = true;
    }


    public function close(): void
    {
        $this->showDetailModal = false;
    }

    public function render()
    {
        return view('livewire.admin.tasks.admin-task-detail');
    }
}
