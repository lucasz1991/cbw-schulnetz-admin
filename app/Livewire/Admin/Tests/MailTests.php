<?php

namespace App\Livewire\Admin\Tests;

use App\Models\Mail;
use Livewire\Component;

class MailTests extends Component
{
    public string $email = '';

    public function openMailComposer(): void
    {
        $validated = $this->validate([
            'email' => 'required|email|max:255',
        ], [
            'email.required' => 'Bitte gib eine Mailadresse ein.',
            'email.email' => 'Bitte gib eine gültige Mailadresse ein.',
        ]);

        $this->dispatch('openMailModal', [
            'emails' => [$validated['email']],
            'forceMailOnly' => true,
        ]);
    }

    protected function isDirectMail(Mail $mail): bool
    {
        $recipients = is_array($mail->recipients) ? $mail->recipients : [];

        if (empty($recipients)) {
            return false;
        }

        return collect($recipients)->every(function (array $recipient): bool {
            return empty($recipient['user_id']) && ! empty($recipient['email']);
        });
    }

    public function render()
    {
        $defaultMailer = (string) config('mail.default');
        $transport = (string) config("mail.mailers.{$defaultMailer}.transport", '');

        $recentMails = Mail::query()
            ->latest()
            ->take(25)
            ->get()
            ->filter(fn (Mail $mail) => $this->isDirectMail($mail))
            ->take(10)
            ->values();

        return view('livewire.admin.tests.mail-tests', [
            'defaultMailer' => $defaultMailer,
            'transport' => $transport,
            'fromAddress' => (string) config('mail.from.address'),
            'fromName' => (string) config('mail.from.name'),
            'recentMails' => $recentMails,
        ])->layout('layouts.master');
    }
}
