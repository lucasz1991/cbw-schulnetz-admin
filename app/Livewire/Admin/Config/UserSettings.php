<?php

namespace App\Livewire\Admin\Config;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

class UserSettings extends Component
{
    public int $openBeforeDays = 14;   // VOR Kursstart
    public int $closeAfterDays = 7;    // NACH Kursende

    /** Master Password UI-State */
    public bool $masterIsActive = false;
    public ?string $masterExpiresAt = null;

    /** Formfelder */
    public ?string $newMasterPassword = null;
    public ?string $newMasterPassword_confirmation = null;
    public string $validFor = '3600'; // default 1h (in Sekunden)
    public array $testUsers = [];
    public array $storedTestUsers = [];
    public bool $showTestUserModal = false;
    public string $testUserModalRole = 'tutor';
    public string $testUserSearch = '';

    public array $dayOptions = [
        3   => '3 Tage',
        5   => '5 Tage',
        7   => '1 Woche',
        14  => '2 Wochen',
        30  => '1 Monat',
        90  => '3 Monate',
        180 => '6 Monate',
        356 => '1 Jahr',
        5 * 356 => '5 Jahre',
        10 * 356 => '10 Jahre',
        20 * 356 => '20 Jahre',
    ];

    /** Gültigkeits-Optionen (Sekunden) */
    public array $validForOptions = [
        '900'    => '15 Minuten',
        '1800'   => '30 Minuten',
        '3600'   => '1 Stunde',
        '21600'  => '6 Stunden',
        '43200'  => '12 Stunden',
        '86400'  => '24 Stunden',
        '259200' => '3 Tage',
        '604800' => '7 Tage',
    ];

    public function mount()
    {
        $allowed = array_keys($this->dayOptions);

        $storedOpen = (int) (Setting::getValue('course_registration', 'open_before_start_days') ?? 14);
        $storedClose = (int) (Setting::getValue('course_registration', 'close_after_end_days') ?? 7);

        $this->openBeforeDays = in_array($storedOpen, $allowed, true) ? $storedOpen : 14;
        $this->closeAfterDays = in_array($storedClose, $allowed, true) ? $storedClose : 7;

        $hash = Setting::getValue('auth', 'master_password_hash');
        $exp = Setting::getValue('auth', 'master_password_expires_at');

        $this->masterExpiresAt = $exp ?: null;
        $this->masterIsActive = $hash && $exp && Carbon::now()->lt(Carbon::parse($exp));

        $this->storedTestUsers = $this->normalizeStoredTestUsers(
            Setting::getValue('auth', 'test_users')
        );

        $this->testUsers = [
            'tutor' => [
                'user_id' => data_get($this->storedTestUsers, 'tutor.user_id'),
                'password' => '',
                'anonymize_output' => (bool) data_get($this->storedTestUsers, 'tutor.anonymize_output', false),
            ],
            'guest' => [
                'user_id' => data_get($this->storedTestUsers, 'guest.user_id'),
                'password' => '',
                'anonymize_output' => (bool) data_get($this->storedTestUsers, 'guest.anonymize_output', false),
            ],
        ];
    }

    public function save()
    {
        $allowed = array_keys($this->dayOptions);

        $this->validate([
            'openBeforeDays' => ['required', 'integer', Rule::in($allowed)],
            'closeAfterDays' => ['required', 'integer', Rule::in($allowed)],
            'testUsers.tutor.user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'tutor')),
            ],
            'testUsers.guest.user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'guest')),
            ],
            'testUsers.tutor.password' => ['nullable', 'string', 'min:6'],
            'testUsers.guest.password' => ['nullable', 'string', 'min:6'],
            'testUsers.tutor.anonymize_output' => ['boolean'],
            'testUsers.guest.anonymize_output' => ['boolean'],
        ]);

        $this->validateTestUsers();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        Setting::setValue('course_registration', 'open_before_start_days', $this->openBeforeDays);
        Setting::setValue('course_registration', 'close_after_end_days', $this->closeAfterDays);
        Setting::setValue('auth', 'test_users', $this->buildTestUsersPayload());

        $this->storedTestUsers = $this->normalizeStoredTestUsers(
            Setting::getValue('auth', 'test_users')
        );

        data_set($this->testUsers, 'tutor.password', '');
        data_set($this->testUsers, 'guest.password', '');

        session()->flash('success', 'Benutzereinstellungen gespeichert.');
        $this->dispatch('$refresh');
    }

    public function setMasterPassword()
    {
        $this->validate([
            'newMasterPassword' => ['required', 'string', 'min:10', 'confirmed'],
            'validFor' => ['required', Rule::in(array_keys($this->validForOptions))],
        ], [], [
            'newMasterPassword' => 'Master-Passwort',
            'validFor' => 'Gültigkeitsdauer',
        ]);

        $hash = Hash::make($this->newMasterPassword);
        $expiresAt = Carbon::now()->addSeconds((int) $this->validFor)->toIso8601String();

        Setting::setValue('auth', 'master_password_hash', $hash);
        Setting::setValue('auth', 'master_password_expires_at', $expiresAt);

        $this->reset(['newMasterPassword', 'newMasterPassword_confirmation']);
        $this->masterExpiresAt = $expiresAt;
        $this->masterIsActive = true;

        session()->flash('success', 'Master-Passwort gesetzt.');
        $this->dispatch('$refresh');
    }

    public function revokeMasterPassword()
    {
        Setting::setValue('auth', 'master_password_hash', null);
        Setting::setValue('auth', 'master_password_expires_at', null);

        $this->masterIsActive = false;
        $this->masterExpiresAt = null;

        session()->flash('success', 'Master-Passwort deaktiviert.');
        $this->dispatch('$refresh');
    }

    public function openTestUserModal(string $role): void
    {
        if (! in_array($role, ['tutor', 'guest'], true)) {
            return;
        }

        $this->testUserModalRole = $role;
        $this->testUserSearch = '';
        $this->showTestUserModal = true;
    }

    public function closeTestUserModal(): void
    {
        $this->showTestUserModal = false;
        $this->testUserSearch = '';
    }

    public function selectTestUser(int $userId): void
    {
        $role = $this->testUserModalRole;

        $user = User::query()
            ->whereKey($userId)
            ->where('role', $role)
            ->first();

        if (! $user) {
            $this->addError("testUsers.{$role}.user_id", 'Der ausgewählte Benutzer passt nicht zum Bereich.');

            return;
        }

        $currentUserId = (int) (data_get($this->testUsers, "{$role}.user_id") ?? 0);

        data_set($this->testUsers, "{$role}.user_id", $user->id);

        if ($currentUserId !== $user->id) {
            data_set($this->testUsers, "{$role}.password", '');
        }

        $this->resetValidation("testUsers.{$role}.user_id");
        $this->closeTestUserModal();
    }

    public function clearTestUser(string $role): void
    {
        if (! in_array($role, ['tutor', 'guest'], true)) {
            return;
        }

        data_set($this->testUsers, "{$role}.user_id", null);
        data_set($this->testUsers, "{$role}.password", '');
        data_set($this->testUsers, "{$role}.anonymize_output", false);

        $this->resetValidation([
            "testUsers.{$role}.user_id",
            "testUsers.{$role}.password",
            "testUsers.{$role}.anonymize_output",
        ]);
    }

    public function getTestUserRoleLabel(string $role): string
    {
        return $role === 'tutor' ? 'Dozenten' : 'Teilnehmer';
    }

    public function getTestUserRoleSingularLabel(string $role): string
    {
        return $role === 'tutor' ? 'Dozent' : 'Teilnehmer';
    }

    public function render()
    {
        $selectedUserIds = collect($this->testUsers)
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $selectedTestUsers = User::query()
            ->with('person')
            ->whereIn('id', $selectedUserIds)
            ->get()
            ->keyBy('id');

        $modalUsers = User::query()
            ->with('person')
            ->where('role', $this->testUserModalRole)
            ->where(function ($query) {
                $search = trim($this->testUserSearch);

                if ($search === '') {
                    return;
                }

                $query
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhereHas('person', function ($personQuery) use ($search) {
                        $personQuery
                            ->where('nachname', 'like', '%' . $search . '%')
                            ->orWhere('vorname', 'like', '%' . $search . '%');
                    });
            })
            ->orderByDesc('status')
            ->orderBy('name')
            ->limit(20)
            ->get();

        return view('livewire.admin.config.user-settings', [
            'selectedTestUsers' => $selectedTestUsers,
            'modalUsers' => $modalUsers,
            'storedTestUserPasswords' => [
                'tutor' => $this->hasReusableStoredPassword('tutor'),
                'guest' => $this->hasReusableStoredPassword('guest'),
            ],
        ]);
    }

    protected function validateTestUsers(): void
    {
        foreach (['tutor', 'guest'] as $role) {
            $userId = data_get($this->testUsers, "{$role}.user_id");
            $password = trim((string) data_get($this->testUsers, "{$role}.password", ''));
            $storedUserId = data_get($this->storedTestUsers, "{$role}.user_id");

            if (blank($userId)) {
                if ($password !== '') {
                    $this->addError("testUsers.{$role}.password", 'Bitte zuerst einen Benutzer auswählen.');
                }

                continue;
            }

            $requiresNewPassword = (int) $storedUserId !== (int) $userId || ! $this->hasStoredPassword($role);

            if ($requiresNewPassword && $password === '') {
                $this->addError("testUsers.{$role}.password", 'Bitte ein Passwort für diesen Testbenutzer hinterlegen.');
            }
        }
    }

    protected function buildTestUsersPayload(): array
    {
        $payload = [];

        foreach (['tutor', 'guest'] as $role) {
            $userId = data_get($this->testUsers, "{$role}.user_id");
            $password = trim((string) data_get($this->testUsers, "{$role}.password", ''));
            $storedUserId = data_get($this->storedTestUsers, "{$role}.user_id");
            $storedPassword = data_get($this->storedTestUsers, "{$role}.password");
            $anonymizeOutput = (bool) data_get($this->testUsers, "{$role}.anonymize_output", false);

            if (blank($userId)) {
                $payload[$role] = [
                    'user_id' => null,
                    'password' => null,
                    'anonymize_output' => false,
                ];

                continue;
            }

            $payload[$role] = [
                'user_id' => (int) $userId,
                'password' => $password !== ''
                    ? Crypt::encryptString($password)
                    : ((int) $storedUserId === (int) $userId ? $storedPassword : null),
                'anonymize_output' => $anonymizeOutput,
            ];
        }

        return $payload;
    }

    protected function normalizeStoredTestUsers(mixed $stored): array
    {
        $defaults = [
            'tutor' => ['user_id' => null, 'password' => null, 'anonymize_output' => false],
            'guest' => ['user_id' => null, 'password' => null, 'anonymize_output' => false],
        ];

        if (! is_array($stored)) {
            return $defaults;
        }

        foreach (array_keys($defaults) as $role) {
            $userId = data_get($stored, "{$role}.user_id");
            $password = data_get($stored, "{$role}.password");

            $defaults[$role] = [
                'user_id' => is_numeric($userId) ? (int) $userId : null,
                'password' => is_string($password) && trim($password) !== '' ? $password : null,
                'anonymize_output' => (bool) data_get($stored, "{$role}.anonymize_output", false),
            ];
        }

        return $defaults;
    }

    protected function hasStoredPassword(string $role): bool
    {
        return filled(data_get($this->storedTestUsers, "{$role}.password"));
    }

    protected function hasReusableStoredPassword(string $role): bool
    {
        $currentUserId = data_get($this->testUsers, "{$role}.user_id");
        $storedUserId = data_get($this->storedTestUsers, "{$role}.user_id");

        return (int) $currentUserId === (int) $storedUserId
            && $this->hasStoredPassword($role);
    }
}
