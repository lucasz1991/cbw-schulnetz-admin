<?php

namespace App\Livewire\Admin\Assets;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ExamAppointment;
use Illuminate\Support\Carbon;

class ExamAppointmentsManagement extends Component
{
    use WithPagination;

    // eigene Page-Namen für zwei Paginatoren
    protected string $paginationTheme = 'tailwind';

    public string $search = '';
    public int $perPage = 10;

    public bool $showModal = false;
    public ?int $editingId = null;

    // Form-Felder
    public string $type = 'intern';            // intern | extern
    public ?string $name = null;
    public ?string $preis = null;
    public ?string $date = null;               // YYYY-MM-DD
    public ?string $time = null;               // HH:MM
    public ?string $room = null;
    public ?int $course_id = null;             // optional, aktuell ohne FK
    public bool $pflicht_6w_anmeldung = false;

    protected function rules(): array
    {
        return [
            'type'                  => 'required|in:intern,extern',
            'name'                  => 'required|string|max:255',
            'preis'                 => 'nullable|numeric|min:0',
            'date'                  => 'required|date',
            'time'                  => 'required|date_format:H:i',
            'room'                  => 'nullable|string|max:100',
            'course_id'             => 'nullable|integer',
            'pflicht_6w_anmeldung'  => 'boolean',
        ];
    }

    public function updatingSearch(): void
    {
        // bei Suche beide Paginatoren zurücksetzen
        $this->resetPage('internPage');
        $this->resetPage('externPage');
    }

    public function create(): void
    {
        $this->reset([
            'editingId','type','name','preis','date','time','room','course_id','pflicht_6w_anmeldung'
        ]);
        $this->type = 'intern';
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $ap = ExamAppointment::findOrFail($id);

        $this->editingId = $ap->id;
        $this->type = $ap->type;
        $this->name = $ap->name;
        $this->preis = $ap->preis !== null ? (string)$ap->preis : null;
        $this->room = $ap->room ?? null; // falls du das Feld später ergänzt
        $this->course_id = $ap->course_id ?? null; // falls du das Feld später ergänzt
        $this->pflicht_6w_anmeldung = (bool)$ap->pflicht_6w_anmeldung;

        $termin = Carbon::parse($ap->termin);
        $this->date = $termin->toDateString();     // YYYY-MM-DD
        $this->time = $termin->format('H:i');      // HH:MM

        $this->showModal = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        // Termin zusammensetzen
        $termin = Carbon::parse("{$data['date']} {$data['time']}:00");

        $payload = [
            'type'                 => $data['type'],
            'name'                 => $data['name'],
            'preis'                => $data['preis'],
            'termin'               => $termin,
            'room'                 => $this->room,        // falls du Spalte ergänzt
            'course_id'            => $this->course_id,   // falls du Spalte ergänzt
            'pflicht_6w_anmeldung' => $this->pflicht_6w_anmeldung,
        ];

        if ($this->editingId) {
            ExamAppointment::findOrFail($this->editingId)->update($payload);
        } else {
            ExamAppointment::create($payload);
        }

        $this->showModal = false;
        $this->dispatch('toast', 'Prüfung gespeichert', 'success');

        // nach Speichern auf Seite 1 zurück, damit man den neuen Eintrag sicher sieht
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
            ->orderByDesc('termin');

        $internAppointments = (clone $base)
            ->where('type', 'intern')
            ->paginate($this->perPage, ['*'], 'internPage');

        $externAppointments = (clone $base)
            ->where('type', 'extern')
            ->paginate($this->perPage, ['*'], 'externPage');

        return view('livewire.admin.assets.exam-appointments-management', [
            'internAppointments' => $internAppointments,
            'externAppointments' => $externAppointments,
        ])->title('Prüfungsverwaltung')->layout('layouts.master');
    }
}
