<?php

namespace App\Livewire;

use Livewire\Component;

class UserAlert extends Component
{
    public string $message = 'Standardnachricht';

    public string $type = 'info';

    public array $typeLabels = [
        'info' => 'Information',
        'success' => 'Erfolgreich',
        'warning' => 'Warnung',
        'error' => 'Fehler',
        'danger' => 'Achtung',
        'question' => 'Frage',
        'notice' => 'Hinweis',
    ];

    protected $listeners = [
        'showAlert' => 'displayAlert',
        'toast' => 'displayToast',
    ];

    public function displayToast(mixed $message, string $type = 'info', array $options = []): void
    {
        $payload = $this->normalizePayload($message, $type, $options);

        $this->dispatch(
            'swal:toast',
            type: $payload['type'],
            title: $payload['title'],
            text: $payload['text'],
            html: $payload['html'],
            position: $payload['position'] ?? null,
            timer: $payload['timer'] ?? null,
            redirectTo: $payload['redirectTo'] ?? null,
            confirmText: $payload['confirmText'] ?? 'OK',
            showConfirm: $payload['showConfirm'] ?? null
        );
    }

    public function displayAlert(mixed $message, string $type = 'info', array $options = []): void
    {
        $payload = $this->normalizePayload($message, $type, $options);

        $this->dispatch(
            'swal:alert',
            type: $payload['type'],
            title: $payload['title'],
            text: $payload['text'],
            html: $payload['html'],
            showCancel: $payload['showCancel'] ?? false,
            confirmText: $payload['confirmText'] ?? 'OK',
            cancelText: $payload['cancelText'] ?? 'Abbrechen',
            allowOutsideClick: $payload['allowOutsideClick'] ?? true,
            onConfirm: $payload['onConfirm'] ?? null,
            redirectTo: $payload['redirectTo'] ?? null,
            redirectOn: $payload['redirectOn'] ?? 'confirm'
        );
    }

    protected function normalizePayload(mixed $message, string $type = 'info', array $options = []): array
    {
        $payload = is_array($message)
            ? $message
            : array_merge(['message' => $message], $options);

        $typeKey = $payload['type'] ?? $type;
        $content = $payload['message'] ?? $payload['text'] ?? null;
        $html = $payload['html'] ?? null;
        $text = $payload['text'] ?? null;

        if ($html === null && $text === null && $content !== null) {
            if (is_string($content) && $this->containsHtml($content)) {
                $html = $content;
            } else {
                $text = $content;
            }
        }

        return array_merge($payload, [
            'type' => $typeKey,
            'title' => $payload['title'] ?? ($this->typeLabels[$typeKey] ?? ucfirst($typeKey)),
            'text' => $text,
            'html' => $html,
        ]);
    }

    protected function containsHtml(string $value): bool
    {
        return $value !== strip_tags($value);
    }

    public function render()
    {
        return view('livewire.user-alert');
    }
}
