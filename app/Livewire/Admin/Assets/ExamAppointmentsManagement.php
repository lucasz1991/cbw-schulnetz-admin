<?php

namespace App\Livewire\Admin\Assets;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ExamAppointment;
use Illuminate\Support\Carbon;

class ExamAppointmentsManagement extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public string $search = '';
    public int $perPage = 10;

    public bool $showModal = false;
    public ?int $editingId = null;

    // Form-Felder
    public string $type = 'intern'; // intern | extern
    public ?string $name = null;
    public ?string $preis = null;
    public ?string $room = null;
    public bool $pflicht_6w_anmeldung = false;

    // Mehrere Termine
    public array $dates = [
        ['date' => null, 'time' => null],
    ];

    // ------------------------------
    // Regeln
    // ------------------------------
    protected function rules(): array
    {
        return [
            'type'  => 'required|in:intern,extern',

            // Name nur bei extern required
            'name'  => 'required_if:type,extern|nullable|string|max:255',

            'preis' => 'nullable|numeric|min:0',

            'dates'            => 'required|array|min:1',
            'dates.*.date'     => 'required|date',
            'dates.*.time'     => 'required|date_format:H:i',

            'room'             => 'nullable|string|max:100',

            'pflicht_6w_anmeldung' => 'boolean',
        ];
    }

    // ------------------------------
    // Livewire Hooks
    // ------------------------------
    public function updatingSearch(): void
    {
        $this->resetPage('internPage');
        $this->resetPage('externPage');
    }

    // ------------------------------
    // UI-Methoden
    // ------------------------------
    public function create(): void
    {
        $this->reset([
            'editingId',
            'type',
            'name',
            'preis',
            'room',
            'pflicht_6w_anmeldung',
            'dates',
        ]);

        $this->type = 'intern';
        $this->dates = [
            ['date' => null, 'time' => null],
        ];

        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $ap = ExamAppointment::findOrFail($id);

        $this->editingId            = $ap->id;
        $this->type                 = $ap->type;
        $this->name                 = $ap->name;
        $this->preis                = $ap->preis !== null ? (string) $ap->preis : null;
        $this->room                 = $ap->room ?? null;
        $this->pflicht_6w_anmeldung = (bool) $ap->pflicht_6w_anmeldung;

        // JSON -> Form-Array
        $this->dates = [];

        if (is_array($ap->dates) && count($ap->dates)) {
            foreach ($ap->dates as $entry) {
                $dt = $entry['datetime'] ?? $entry['from'] ?? null;
                if (! $dt) {
                    continue;
                }

                $c = Carbon::parse($dt);
                $this->dates[] = [
                    'date' => $c->toDateString(), // YYYY-MM-DD
                    'time' => $c->format('H:i'),   // HH:MM
                ];
            }
        }

        if (empty($this->dates)) {
            $this->dates = [
                ['date' => null, 'time' => null],
            ];
        }

        $this->showModal = true;
    }

    public function addDate(): void
    {
        $this->dates[] = ['date' => null, 'time' => null];
    }

    public function removeDate(int $index): void
    {
        unset($this->dates[$index]);
        $this->dates = array_values($this->dates);
    }

    public function save(): void
    {
        $data = $this->validate();

        // Name für interne Prüfungen setzen
        if ($data['type'] === 'intern') {
            $data['name'] = 'Nachklausur';
        }

        // dates[] -> JSON-Array
        $datesPayload = collect($data['dates'])
            ->map(function ($row) {
                $dt = Carbon::parse($row['date'].' '.$row['time'].':00');
                return [
                    'datetime' => $dt->toDateTimeString(),
                ];
            })
            ->values()
            ->all();

        $payload = [
            'type'                 => $data['type'],
            'name'                 => $data['name'],
            'preis'                => $data['preis'],
            'dates'                => $datesPayload,
            'room'                 => $this->room,
            'pflicht_6w_anmeldung' => $this->pflicht_6w_anmeldung,
        ];

        if ($this->editingId) {
            ExamAppointment::findOrFail($this->editingId)->update($payload);
        } else {
            ExamAppointment::create($payload);
        }

        $this->showModal = false;
        $this->dispatch('toast', 'Prüfung gespeichert', 'success');

        $this->resetPage('internPage');
        $this->resetPage('externPage');
    }

    public function delete(int $id): void
    {
        ExamAppointment::findOrFail($id)->delete();
        $this->dispatch('toast', 'Prüfung gelöscht', 'success');

        $this->resetPage('internPage');
        $this->resetPage('externPage');
    }

    // ------------------------------
    // Render
    // ------------------------------
    public function render()
    {
        $base = ExamAppointment::query()
            ->when($this->search, function ($q) {
                $s = $this->search;
                $q->where(function ($qq) use ($s) {
                    $qq->where('name', 'like', "%{$s}%")
                       ->orWhere('type', 'like', "%{$s}%")
                       ->orWhere('room', 'like', "%{$s}%");
                });
            })
            // nach erstem Termin sortieren (JSON -> dates[0].datetime)
            ->orderBy('dates->0->datetime', 'desc');

        $internAppointments = (clone $base)
            ->where('type', 'intern')
            ->paginate($this->perPage, ['*'], 'internPage');

        $externAppointments = (clone $base)
            ->where('type', 'extern')
            ->paginate($this->perPage, ['*'], 'externPage');

        return view('livewire.admin.assets.exam-appointments-management', [
            'internAppointments' => $internAppointments,
            'externAppointments' => $externAppointments,
        ])
            ->title('Prüfungsverwaltung')
            ->layout('layouts.master');
    }
}
