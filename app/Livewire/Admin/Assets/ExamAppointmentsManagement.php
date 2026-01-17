<?php

namespace App\Livewire\Admin\Assets;

use App\Models\ExamAppointment;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

class ExamAppointmentsManagement extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    /** URL Persist (Tabs + Suche + PerPage) */
    public string $tab = 'intern'; // intern | extern
    public string $search = '';
    public int $perPage = 10;

    public bool $showModal = false;
    public ?int $editingId = null;

    // Form-Felder
    public string $type = 'intern'; // intern | extern
    public ?string $name = 'Nachklausur';
    public ?string $preis = null;
    public ?string $room = null;
    public bool $pflicht_6w_anmeldung = false;

    // Ein Termin (intern), bei extern optional leer
    public array $dates = [
        ['date' => null, 'time' => null],
    ];

    /** getrennte Pagination Keys */
    protected string $internPageName = 'internPage';
    protected string $externPageName = 'externPage';

    /** Querystring Sync */
    protected array $queryString = [
        'tab' => ['except' => 'intern'],
        'search' => ['except' => ''],
        'perPage' => ['except' => 10],
    ];

    // ------------------------------
    // Lifecycle
    // ------------------------------
    public function mount(): void
    {
        $this->tab = in_array($this->tab, ['intern', 'extern'], true) ? $this->tab : 'intern';
    }

    public function updatingSearch(): void
    {
        $this->resetPages();
    }

    public function updatingPerPage(): void
    {
        $this->resetPages();
    }

    public function updatedTab($value): void
    {
        $this->tab = in_array($value, ['intern', 'extern'], true) ? $value : 'intern';
        // optional: Beim Tab-Wechsel beide Listen auf Seite 1
        $this->resetPages();
    }

    // ------------------------------
    // Regeln
    // ------------------------------
    protected function rules(): array
    {
        return [
            'type'  => 'required|in:intern,extern',
            'name'  => 'required_if:type,extern|nullable|string|max:255',
            'preis' => 'required_if:type,extern|nullable|numeric|min:0|max:10000',
            'dates' => 'array',
            'dates.0.date' => 'required_if:type,intern|nullable|date',
            'dates.0.time' => 'required_if:type,intern|nullable|date_format:H:i',
            'room' => 'nullable|string|max:100',
            'pflicht_6w_anmeldung' => 'boolean',
        ];
    }

    protected function messages(): array
    {
        return [
            'type.required' => 'Bitte wählen Sie einen Typ aus.',
            'name.required_if' => 'Bitte geben Sie einen Namen ein.',
            'preis.required_if' => 'Bitte geben Sie einen Preis ein.',
            'preis.min' => 'Der Preis muss mindestens 0 sein.',
            'preis.max' => 'Der Preis darf maximal 10000 sein.',
            'dates.0.date.required_if' => 'Bitte wählen Sie ein Datum aus.',
            'dates.0.time.required_if' => 'Bitte wählen Sie eine Uhrzeit aus.',
            'room.max' => 'Der Raumname darf maximal 100 Zeichen lang sein.',
        ];
    }

    // ------------------------------
    // Helpers
    // ------------------------------
    protected function resetPages(): void
    {
        $this->resetPage($this->internPageName);
        $this->resetPage($this->externPageName);
    }

    protected function blankDates(): array
    {
        return [['date' => null, 'time' => null]];
    }

    protected function buildDatesPayload(array $data): array
    {
        if (($data['type'] ?? 'intern') !== 'intern') {
            return [];
        }

        $row = $data['dates'][0] ?? ['date' => null, 'time' => null];
        if (empty($row['date']) || empty($row['time'])) {
            return [];
        }

        $dt = Carbon::parse($row['date'] . ' ' . $row['time'] . ':00');

        return [
            ['datetime' => $dt->toDateTimeString()],
        ];
    }

    // ------------------------------
    // UI-Methoden
    // ------------------------------
    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['intern', 'extern'], true) ? $tab : 'intern';
        $this->resetPages();
    }

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
        $this->name = 'Nachklausur';
        $this->dates = $this->blankDates();

        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $ap = ExamAppointment::findOrFail($id);

        $this->editingId            = $ap->id;
        $this->type                 = $ap->type ?? 'intern';
        $this->name                 = $ap->name ?? ($this->type === 'intern' ? 'Nachklausur' : '');
        $this->preis                = $ap->preis !== null ? (string) $ap->preis : null;
        $this->room                 = $ap->room ?? null;
        $this->pflicht_6w_anmeldung = (bool) $ap->pflicht_6w_anmeldung;

        // JSON -> Form-Array (nur relevant für intern)
        $this->dates = [];

        if ($this->type === 'intern' && is_array($ap->dates) && count($ap->dates)) {
            $entry = $ap->dates[0] ?? null;
            $dt = $entry['datetime'] ?? $entry['from'] ?? null;

            if ($dt) {
                $c = Carbon::parse($dt);
                $this->dates[] = [
                    'date' => $c->toDateString(),
                    'time' => $c->format('H:i'),
                ];
            }
        }

        if ($this->type === 'intern' && empty($this->dates)) {
            $this->dates = $this->blankDates();
        }

        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        $payload = [
            'type'                 => $data['type'],
            'name'                 => $data['type'] === 'intern' ? ($this->name ?: 'Nachklausur') : ($this->name ?? ''),
            'preis'                => $data['type'] === 'extern' ? $this->preis : null,
            'dates'                => $this->buildDatesPayload($data),
            'room'                 => $this->room,
            'pflicht_6w_anmeldung' => $data['type'] === 'extern' ? (bool) $this->pflicht_6w_anmeldung : false,
        ];

        if ($this->editingId) {
            ExamAppointment::findOrFail($this->editingId)->update($payload);
        } else {
            ExamAppointment::create($payload);
        }

        $this->showModal = false;

        // Tab nach Save passend setzen (damit UX stimmt)
        $this->tab = $data['type'];

        $this->dispatch('toast', 'Prüfung gespeichert', 'success');

        $this->resetPages();
    }

    public function delete(int $id): void
    {
        ExamAppointment::findOrFail($id)->delete();

        $this->dispatch('toast', 'Prüfung gelöscht', 'success');

        $this->resetPages();
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
            ->paginate($this->perPage, ['*'], $this->internPageName);

        $externAppointments = (clone $base)
            ->where('type', 'extern')
            ->paginate($this->perPage, ['*'], $this->externPageName);

        return view('livewire.admin.assets.exam-appointments-management', [
            'internAppointments' => $internAppointments,
            'externAppointments' => $externAppointments,
        ])
            ->title('Prüfungsverwaltung')
            ->layout('layouts.master');
    }
}
