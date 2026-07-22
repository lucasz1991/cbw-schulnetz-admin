<?php

namespace App\Livewire\Admin\Employees;

use App\Models\Team;
use App\Support\Rbac\RbacCatalog;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class TeamRbacModal extends Component
{
    public bool $showModal = false;
    public ?int $selectedTeamId = null;

    /**
     * Matrix: team_id => permission_key => bool
     *
     * @var array<string, array<string, bool>>
     */
    public array $matrix = [];

    #[On('open-team-rbac-modal')]
    public function open(): void
    {
        Gate::authorize('roles.manage');

        $this->showModal = true;
        $this->loadMatrix();

        if ($this->selectedTeamId === null) {
            $firstTeamId = $this->teams()->pluck('id')->first();
            $this->selectedTeamId = $firstTeamId ? (int) $firstTeamId : null;
        }

        if ($this->selectedTeamId !== null) {
            $this->initializeTeam((int) $this->selectedTeamId);
        }
    }

    public function close(): void
    {
        $this->showModal = false;
    }

    public function setTeam(int $teamId): void
    {
        $this->selectedTeamId = $teamId;
        $this->initializeTeam($teamId);
    }

    public function setSelectedTeamToFalse(): void
    {
        Gate::authorize('roles.manage');

        if ($this->selectedTeamId === null) {
            return;
        }

        $teamId = (int) $this->selectedTeamId;
        $this->matrix[(string) $teamId] = $this->defaultMatrixForTeam();
    }

    public function setSelectedTeamToTrue(): void
    {
        Gate::authorize('roles.manage');

        if ($this->selectedTeamId === null) {
            return;
        }

        $teamId = (int) $this->selectedTeamId;
        $permissions = $this->permissionsForCurrentUser();
        $permissionMatrix = [];
        foreach ($permissions as $permission) {
            $encodedPermission = $this->permissionKey($permission);
            $permissionMatrix[$encodedPermission] = true;
        }

        $this->matrix[(string) $teamId] = $permissionMatrix;
    }

    public function save(): void
    {
        Gate::authorize('roles.manage');

        $teams = $this->teams();
        $teamIds = $teams->pluck('id')->map(fn ($id) => (int) $id)->all();
        $teamSet = array_flip($teamIds);
        $permissions = $this->permissionsForCurrentUser();
        $permissionMap = [];
        foreach ($permissions as $permission) {
            $permissionMap[$this->permissionKey($permission)] = $permission;
        }

        $legacy = \App\Models\Setting::getValue('rbac', 'team_permissions');
        $legacy = is_array($legacy) ? $legacy : [];

        foreach ($this->matrix as $teamId => $permissionMatrix) {
            $teamId = (int) $teamId;
            if (! isset($teamSet[$teamId])) {
                continue;
            }

            $team = $teams->firstWhere('id', $teamId);
            $stored = is_array($team?->rbac_permissions) ? $team->rbac_permissions : [];
            $payload = auth()->user()?->isAdmin()
                ? []
                : ($stored ?: (is_array($legacy[(string) $teamId] ?? null) ? $legacy[(string) $teamId] : []));

            foreach ($permissionMap as $encodedPermission => $permission) {
                $payload[$permission] = (bool) ($permissionMatrix[$encodedPermission] ?? false);
            }

            Team::query()->where('id', $teamId)->update([
                'rbac_permissions' => $payload,
            ]);
        }

        $this->dispatch('swal:toast', type: 'success', title: 'Gespeichert', text: 'Team-Rechte wurden gespeichert.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Team>
     */
    public function teams()
    {
        return Team::query()
            ->where('id', '!=', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'rbac_permissions']);
    }

    protected function loadMatrix(): void
    {
        $this->matrix = [];
        $permissions = $this->permissionsForCurrentUser();
        $legacy = \App\Models\Setting::getValue('rbac', 'team_permissions');
        $legacy = is_array($legacy) ? $legacy : [];

        foreach ($this->teams() as $team) {
            $teamPermissions = [];
            $stored = is_array($team->rbac_permissions) ? $team->rbac_permissions : [];

            foreach ($permissions as $permission) {
                $encodedPermission = $this->permissionKey($permission);
                $teamPermissions[$encodedPermission] = (bool) ($stored[$permission] ?? false);
            }

            if (! empty($stored)) {
                $this->matrix[(string) $team->id] = $teamPermissions;
                continue;
            }

            // Legacy-Fallback: aus settings lesen (falls alte Daten existieren)
            if (isset($legacy[(string) $team->id]) && is_array($legacy[(string) $team->id])) {
                foreach ($permissions as $permission) {
                    $encodedPermission = $this->permissionKey($permission);
                    $teamPermissions[$encodedPermission] = (bool) ($legacy[(string) $team->id][$permission] ?? false);
                }
            }

            $this->matrix[(string) $team->id] = $teamPermissions;
        }
    }

    protected function initializeTeam(int $teamId): void
    {
        $key = (string) $teamId;
        if (! isset($this->matrix[$key]) || ! is_array($this->matrix[$key])) {
            $this->matrix[$key] = $this->defaultMatrixForTeam();
            return;
        }

        $permissions = $this->permissionsForCurrentUser();
        foreach ($permissions as $permission) {
            $encodedPermission = $this->permissionKey($permission);
            $current = $this->matrix[$key][$encodedPermission] ?? false;
            $this->matrix[$key][$encodedPermission] = (bool) $current;
        }
    }

    /**
     * @return array<string, bool>
     */
    protected function defaultMatrixForTeam(): array
    {
        $defaults = [];
        $allowed = array_flip($this->permissionsForCurrentUser());
        foreach (RbacCatalog::defaultTeamPermissions() as $permission => $enabled) {
            if (! isset($allowed[$permission])) {
                continue;
            }

            $defaults[$this->permissionKey($permission)] = (bool) $enabled;
        }

        return $defaults;
    }

    public function permissionKey(string $permission): string
    {
        return str_replace('.', '__dot__', $permission);
    }

    /**
     * @return array<int, string>
     */
    protected function permissionsForCurrentUser(): array
    {
        $permissions = RbacCatalog::allPermissions();

        if (auth()->user()?->isAdmin()) {
            return $permissions;
        }

        return array_values(array_diff($permissions, RbacCatalog::adminOnlyPermissions()));
    }

    public function render()
    {
        return view('livewire.admin.employees.team-rbac-modal', [
            'teams' => $this->teams(),
            'permissionGroups' => RbacCatalog::permissionGroups(),
        ]);
    }
}
