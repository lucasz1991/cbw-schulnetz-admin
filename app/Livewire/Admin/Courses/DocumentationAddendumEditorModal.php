<?php

namespace App\Livewire\Admin\Courses;

use App\Models\CourseDay;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class DocumentationAddendumEditorModal extends Component
{
    public bool $showModal = false;
    public ?int $courseDayId = null;
    public string $courseTitle = '';
    public string $dayLabel = '';
    public string $originalNotesHtml = '';
    public string $documentationAddendum = '';
    public int $documentationAddendumStatus = CourseDay::DOCUMENTATION_ADDENDUM_STATUS_DRAFT;
    public ?string $savedByName = null;
    public ?string $savedAt = null;
    public int $editorVersion = 0;

    #[On('openAdminDocumentationAddendumEditor')]
    public function open(mixed $courseDayId = null): void
    {
        $courseDayId = $this->normalizeCourseDayId($courseDayId);
        abort_unless($courseDayId, 404);

        $this->resetValidation();
        $this->loadDay($this->editableDay($courseDayId));
        $this->editorVersion++;
        $this->showModal = true;
    }

    public function save(): void
    {
        abort_unless($this->courseDayId, 404);
        $day = $this->editableDay($this->courseDayId);

        $this->validate([
            'documentationAddendum' => ['nullable', 'string'],
            'documentationAddendumStatus' => [
                'required',
                'integer',
                Rule::in([
                    CourseDay::DOCUMENTATION_ADDENDUM_STATUS_DRAFT,
                    CourseDay::DOCUMENTATION_ADDENDUM_STATUS_PUBLISHED,
                ]),
            ],
        ], [], [
            'documentationAddendum' => 'Dokumentationszusatz',
            'documentationAddendumStatus' => 'Status',
        ]);

        $html = trim($this->documentationAddendum);
        $hasContent = $this->hasMeaningfulContent($html);
        $status = $hasContent
            ? $this->documentationAddendumStatus
            : CourseDay::DOCUMENTATION_ADDENDUM_STATUS_DRAFT;

        $day->documentation_addendum = $hasContent ? $html : null;
        $day->documentation_addendum_status = $status;
        $day->documentation_addendum_saved_by_user_id = Auth::id();
        $day->documentation_addendum_saved_at = now();
        $day->save();

        $this->loadDay($day->fresh(['course', 'documentationAddendumSavedBy']));
        $this->editorVersion++;
        $this->dispatch('adminDocumentationAddendumUpdated', (int) $day->id);
        $this->dispatch(
            'swal:toast',
            type: 'success',
            text: $hasContent
                ? 'Dokumentationszusatz wurde gespeichert.'
                : 'Der Dokumentationszusatz wurde geleert und als Entwurf gespeichert.'
        );
    }

    public function close(): void
    {
        $this->showModal = false;
        $this->courseDayId = null;
        $this->courseTitle = '';
        $this->dayLabel = '';
        $this->originalNotesHtml = '';
        $this->documentationAddendum = '';
        $this->documentationAddendumStatus = CourseDay::DOCUMENTATION_ADDENDUM_STATUS_DRAFT;
        $this->savedByName = null;
        $this->savedAt = null;
        $this->resetValidation();
    }

    protected function editableDay(int $courseDayId): CourseDay
    {
        Gate::authorize('courses.documentation_addendum.edit');

        return CourseDay::query()
            ->with(['course', 'documentationAddendumSavedBy'])
            ->findOrFail($courseDayId);
    }

    protected function loadDay(CourseDay $day): void
    {
        $this->courseDayId = (int) $day->id;
        $this->courseTitle = $day->course?->title ?: 'Baustein #'.$day->course_id;
        $this->dayLabel = $day->date?->locale('de')->isoFormat('dddd, LL') ?: 'Unbekannter Unterrichtstag';
        $this->originalNotesHtml = (string) ($day->notes ?? '');
        $this->documentationAddendum = (string) ($day->documentation_addendum ?? '');
        $this->documentationAddendumStatus = (int) (
            $day->documentation_addendum_status ?? CourseDay::DOCUMENTATION_ADDENDUM_STATUS_DRAFT
        );
        $this->savedByName = $day->documentationAddendumSavedBy?->name;
        $this->savedAt = $day->documentation_addendum_saved_at?->format('d.m.Y H:i');
    }

    protected function normalizeCourseDayId(mixed $payload): ?int
    {
        while (is_array($payload)) {
            $payload = $payload['courseDayId']
                ?? $payload['id']
                ?? (count($payload) === 1 ? reset($payload) : null);
        }

        if (! is_numeric($payload) || (int) $payload <= 0) {
            return null;
        }

        return (int) $payload;
    }

    protected function hasMeaningfulContent(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);

        return trim($text) !== '';
    }

    public function render()
    {
        return view('livewire.admin.courses.documentation-addendum-editor-modal');
    }
}
