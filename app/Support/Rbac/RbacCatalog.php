<?php

namespace App\Support\Rbac;

class RbacCatalog
{
    /**
     * @return array<string, string>
     */
    public static function roles(): array
    {
        return [
            'team_access' => 'Team Access',
        ];
    }

    /**
     * @return array<string, array<int, array{key: string, label: string, admin_only?: bool}>>
     */
    public static function permissionGroups(): array
    {
        return [
            'System' => [
                ['key' => 'settings.manage', 'label' => 'Einstellungen verwalten'],
            ],
            'Employees' => [
                ['key' => 'employees.view', 'label' => 'Mitarbeiter anzeigen'],
                ['key' => 'employees.create', 'label' => 'Mitarbeiter erstellen & bearbeiten'],
                ['key' => 'roles.manage', 'label' => 'Rollen verwalten'],
            ],
            'Management' => [
                ['key' => 'manage.appointments', 'label' => 'Termine anzeigen & verwalten'],
                ['key' => 'manage.messages', 'label' => 'Nachrichten anzeigen & verwalten'],
                ['key' => 'manage.onbording.view', 'label' => 'Onboarding anzeigen & verwalten'],
            ],
            'Benutzer' => [
                ['key' => 'users.view', 'label' => 'Benutzer anzeigen'],
                ['key' => 'users.edit', 'label' => 'Benutzer bearbeiten'],
                ['key' => 'users.profiles.view', 'label' => 'Profile anzeigen'], 
            ],
            'Benutzer - Nachrichten' => [
                ['key' => 'users.messages.view', 'label' => 'Nachrichten anzeigen'],
                ['key' => 'users.messages.create', 'label' => 'Nachrichten erstellen'],
                ['key' => 'users.messages.delete', 'label' => 'Nachrichten löschen'],
            ],
            'Kursverwaltung' => [
                ['key' => 'courses.view', 'label' => 'Kurse anzeigen'],
                ['key' => 'courses.export', 'label' => 'Kurse exportieren'],
                ['key' => 'courses.ratings.view', 'label' => 'Kursbewertungen anzeigen'],
                [
                    'key' => 'courses.attendance.edit_today',
                    'label' => 'Anwesenheit bearbeiten',
                ],
                [
                    'key' => 'courses.documentation_addendum.edit',
                    'label' => 'Dokumentationszusatz freigeben',
                ],
                ['key' => 'invoices.view', 'label' => 'Rechnungen anzeigen'],
            ],
            'Jobs' => [
                ['key' => 'jobs.view', 'label' => 'Jobs anzeigen & bearbeiten'],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function allPermissions(): array
    {
        $all = [];
        foreach (self::permissionGroups() as $permissionItems) {
            foreach ($permissionItems as $item) {
                $key = (string) ($item['key'] ?? '');
                if ($key !== '') {
                    $all[] = $key;
                }
            }
        }

        return array_values(array_unique($all));
    }

    /**
     * @return array<string, string>
     */
    public static function permissionLabels(): array
    {
        $labels = [];
        foreach (self::permissionGroups() as $permissionItems) {
            foreach ($permissionItems as $item) {
                $key = (string) ($item['key'] ?? '');
                if ($key === '') {
                    continue;
                }

                $labels[$key] = (string) ($item['label'] ?? $key);
            }
        }

        return $labels;
    }

    /**
     * @return array<int, string>
     */
    public static function adminOnlyPermissions(): array
    {
        $permissions = [];

        foreach (self::permissionGroups() as $permissionItems) {
            foreach ($permissionItems as $item) {
                if (! ($item['admin_only'] ?? false)) {
                    continue;
                }

                $key = (string) ($item['key'] ?? '');
                if ($key !== '') {
                    $permissions[] = $key;
                }
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function defaultRolePermissions(): array
    {
        return [
            'team_access' => self::allPermissions(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultTeamPermissions(): array
    {
        $defaults = [];
        foreach (self::allPermissions() as $permission) {
            $defaults[$permission] = false;
        }

        return $defaults;
    }
}
