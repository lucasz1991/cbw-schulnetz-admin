<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class Login extends Component
{
    public $message;
    public $messageType;
    public $email = '';
    public $password = '';
    public $remember = false;

    protected $rules = [
        'email' => 'required|email|max:255|exists:users,email',
        'password' => 'required|min:6|max:255',
    ];
    
    protected $messages = [
        'email.required' => 'Bitte gib deine E-Mail-Adresse ein.',
        'email.email' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
        'email.max' => 'Die E-Mail-Adresse darf maximal 255 Zeichen lang sein.',
        'email.exists' => 'Diese E-Mail-Adresse ist nicht registriert.',
        'password.required' => 'Bitte gib dein Passwort ein.',
        'password.min' => 'Das Passwort muss mindestens 6 Zeichen lang sein.',
        'password.max' => 'Das Passwort darf maximal 255 Zeichen lang sein.',
    ];

    public function login()
    {
        $this->validate();

        // 🔹 Login-Versuch
        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            Log::warning('Fehlgeschlagener Loginversuch', [
                'email' => $this->email,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            throw ValidationException::withMessages([
                'email' => 'Die eingegebene E-Mail-Adresse oder das Passwort ist falsch.',
            ]);
        }

        // 🔹 Benutzer holen
        $user = Auth::user();

        // 🔹 Nur admin/staff erlaubt
        if (!in_array($user->role, ['admin', 'staff'])) {

            Log::notice('Login verweigert – keine Admin/Staff-Rolle', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Du hast keinen Zugriff auf das Admin-Panel.',
            ]);
        }

        // 🔹 Erfolgreicher Login
        Log::info('Erfolgreicher Admin/Staff-Login', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'ip' => request()->ip(),
        ]);

        $this->dispatch('showAlert', 'Willkommen zurück!', 'success');

        return $this->redirect('/dashboard');
    }

    public function mount()
    {
        if (session()->has('message')) {
            $this->message = session()->get('message');
            $this->messageType = session()->get('messageType', 'default'); 
            $this->dispatch('showAlert', $this->message, $this->messageType);
        }
    }

    public function render()
    {
        return view('livewire.auth.login')->layout('layouts/master-without-nav');
    }
}
