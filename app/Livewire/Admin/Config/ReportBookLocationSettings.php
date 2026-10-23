<?php

namespace App\Livewire\Admin\Config;

use App\Services\ApiUvs\AssetsApiServices\InstitutionsLoadService;
use App\Services\ReportBooks\LocationPolicy;
use App\Services\ReportBooks\StampStorage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class ReportBookLocationSettings extends Component
{
    use WithFileUploads;

    public string $instituteId = '';
    public bool $required = true;
    public bool $stampEnabled = false;
    public string $publicationDate = '';
    public $stampUpload = null;
    #[Locked] public int $version = 0;
    #[Locked] public array $institutions = [];
    #[Locked] public array $state = [];
    #[Locked] public array $stamp = [];
    #[Locked] public ?string $stampPreview = null;
    #[Locked] public ?array $impact = null;
    #[Locked] public ?string $previewFingerprint = null;

    public function boot(): void
    {
        Gate::authorize('settings.manage');
    }

    public function mount(): void
    {
        $this->institutions = app(InstitutionsLoadService::class)->getInstitutionOptions();
        $this->publicationDate = now('Europe/Berlin')->toDateString();
    }

    public function placeholder(): string
    {
        return '<section class="reportbook-location-settings" aria-busy="true" role="status"><p class="rb-help">Berichtsheft-Einstellungen werden geladen …</p></section>';
    }

    public function updatedInstituteId(): void
    {
        $this->loadState();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['required', 'stampEnabled', 'publicationDate', 'stampUpload'], true)) {
            $this->impact = null;
            $this->previewFingerprint = null;
        }
    }

    public function uploadStamp(): void
    {
        Gate::authorize('settings.manage');
        $this->validate(['stampUpload' => 'required|file|max:4096|mimetypes:image/png,image/jpeg']);
        $this->stamp = StampStorage::store($this->stampUpload);
        $this->stampPreview = StampStorage::preview($this->stamp['stamp_path'], $this->stamp['stamp_sha256'], $this->stamp['stamp_mime']);
        $this->reset('stampUpload');
        $this->impact = null;
        $this->previewFingerprint = null;
    }

    public function preview(): void
    {
        Gate::authorize('settings.manage');
        $input = $this->validatedInput();
        $this->impact = LocationPolicy::preview((int) $this->instituteId);
        $this->previewFingerprint = $this->fingerprint($input);
    }

    public function schedule(): void
    {
        Gate::authorize('settings.manage');
        $input = $this->validatedInput();
        if (! $this->previewFingerprint || ! hash_equals($this->previewFingerprint, $this->fingerprint($input))) {
            throw ValidationException::withMessages(['policy' => 'Bitte die Vorschau für diese Einstellungen zuerst aktualisieren.']);
        }
        LocationPolicy::schedule((int) $this->instituteId, $input, (int) auth()->id(), $this->version);
        $this->loadState();
        session()->flash('reportbook_policy_saved', 'Die Standortregel wurde gespeichert. Der Veröffentlichungszeitpunkt entscheidet über ihre Wirksamkeit.');
    }

    public function cancel(): void
    {
        Gate::authorize('settings.manage');
        $this->validateInstitution();
        LocationPolicy::cancel((int) $this->instituteId, (int) auth()->id(), $this->version);
        $this->loadState();
        session()->flash('reportbook_policy_saved', 'Die geplante Änderung wurde storniert.');
    }

    public function downloadAudit(int $runId)
    {
        Gate::authorize('settings.manage');
        $this->validateInstitution();
        $run = DB::table('report_book_approval_runs as runs')
            ->join('report_book_policy_revisions as revisions', 'revisions.id', '=', 'runs.revision_id')
            ->join('report_book_institute_policies as policies', 'policies.id', '=', 'revisions.policy_id')
            ->where('runs.id', $runId)->where('policies.institut_id', (int) $this->instituteId)
            ->select('runs.id', 'runs.revision_id')->first();
        abort_unless($run, 404);

        return response()->streamDownload(function () use ($run): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Audit-ID', 'Revision', 'Lauf', 'Ereignis', 'Berichtsheft', 'Eintrag', 'Prüfaufgabe', 'Akteur', 'Zeitpunkt (UTC)', 'Nachweis (JSON)'], ';');
            DB::table('report_book_policy_audit')->where('revision_id', $run->revision_id)->orderBy('id')
                ->chunkById(250, function ($rows) use ($output): void {
                    foreach ($rows as $row) {
                        fputcsv($output, [$row->id, $row->revision_id, $row->run_id, $row->event, $row->book_id,
                            $row->entry_id, $row->task_id, $row->actor_id, $row->created_at, $row->payload], ';');
                    }
                });
            fclose($output);
        }, 'berichtsheft-protokoll-lauf-'.$runId.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function loadState(): void
    {
        Gate::authorize('settings.manage');
        $this->validateInstitution();
        $this->resetValidation();
        $this->state = LocationPolicy::currentState((int) $this->instituteId);
        $this->version = (int) ($this->state['version'] ?? 0);
        $selected = $this->state['scheduled'] ?? $this->state['current'] ?? [];
        $this->required = (bool) ($selected['required'] ?? true);
        $this->stampEnabled = (bool) ($selected['stamp_enabled'] ?? false);
        $this->publicationDate = ! empty($this->state['scheduled']['effective_at'])
            ? Carbon::parse($this->state['scheduled']['effective_at'])->timezone('Europe/Berlin')->toDateString()
            : now('Europe/Berlin')->toDateString();
        $this->stamp = array_intersect_key($selected, array_flip(['stamp_path', 'stamp_sha256', 'stamp_mime']));
        $this->stampPreview = ! empty($this->stamp['stamp_path'])
            ? StampStorage::preview($this->stamp['stamp_path'], $this->stamp['stamp_sha256'] ?? '', $this->stamp['stamp_mime'] ?? '') : null;
        $this->impact = null;
        $this->previewFingerprint = null;
        $this->reset('stampUpload');
    }

    private function validateInstitution(): void
    {
        $this->validate(['instituteId' => ['required', 'integer', 'min:1', Rule::in(array_column($this->institutions, 'value'))]]);
        if (! LocationPolicy::schemaReady()) {
            throw ValidationException::withMessages(['policy' => 'Die Berichtsheft-Standortsteuerung ist noch nicht eingerichtet.']);
        }
    }

    private function validatedInput(): array
    {
        $this->validateInstitution();
        $this->validate([
            'required' => 'required|boolean', 'stampEnabled' => 'required|boolean',
            'publicationDate' => 'required|date_format:Y-m-d|after_or_equal:'.now('Europe/Berlin')->toDateString(),
        ]);
        if ($this->stampEnabled && empty($this->stamp['stamp_path'])) {
            throw ValidationException::withMessages(['stampUpload' => 'Bitte zuerst den CBW-Stempel hochladen.']);
        }

        return [
            'required' => $this->required, 'stamp_enabled' => $this->stampEnabled,
            'publication_date' => $this->publicationDate,
            'stamp_path' => $this->stampEnabled ? ($this->stamp['stamp_path'] ?? null) : null,
            'stamp_sha256' => $this->stampEnabled ? ($this->stamp['stamp_sha256'] ?? null) : null,
            'stamp_mime' => $this->stampEnabled ? ($this->stamp['stamp_mime'] ?? null) : null,
        ];
    }

    private function fingerprint(array $input): string
    {
        return hash('sha256', json_encode([$this->instituteId, $this->version, $input], JSON_THROW_ON_ERROR));
    }

    public function render()
    {
        Gate::authorize('settings.manage');

        return view('livewire.admin.config.report-book-location-settings', ['schemaReady' => LocationPolicy::schemaReady()]);
    }
}
