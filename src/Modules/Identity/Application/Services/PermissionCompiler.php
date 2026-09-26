<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

/**
 * Performanz-Compiler für verschachtelte RBAC Berechtigungsbäume.
 * Unterstützt sowohl komplette Kategorie-Freigaben (Master-Key / Wildcard)
 * als auch feingranulare Einzelberechtigungen ohne aktiven Eltern-Schalter.
 */
final class PermissionCompiler
{
    public function compile(array $structure, array $groupPermissions): array
    {
        $flat = [];
        $this->walk($structure, $groupPermissions, false, false, $flat);

        // Implizite Container-Freigaben:
        // 1. Wer Benutzer oder Rollen verwalten darf, benötigt Zugriff auf die Benutzerverwaltung (system.manage)
        if (
            (($flat['system.users.manage'] ?? false) === true || ($flat['system.roles.manage'] ?? false) === true)
            && !\in_array('-system.manage', $groupPermissions, true)
        ) {
            $flat['system.manage'] = true;
        }

        // 2. Wer Wachstums-Diagramme sehen darf, benötigt Zugriff auf den Statistik-Tab (stats.view)
        if (
            ($flat['stats.charts'] ?? false) === true
            && !\in_array('-stats.view', $groupPermissions, true)
        ) {
            $flat['stats.view'] = true;
        }

        return $flat;
    }

    private function walk(
        array $nodes,
        array $groupPerms,
        bool $parentGrantedAll,
        bool $parentDenied,
        array &$result,
    ): void {
        $hasWildcard = \in_array('*', $groupPerms, true);

        foreach ($nodes as $node) {
            $key = $node['key'] ?? null;

            if (\is_string($key) && $key !== '') {
                $explicitAllow = \in_array($key, $groupPerms, true) || $hasWildcard;
                $explicitDeny = \in_array('-' . $key, $groupPerms, true) || $parentDenied;

                $isAllowed = ($parentGrantedAll || $explicitAllow) && !$explicitDeny;
                $result[$key] = $isAllowed;

                $childParentGranted = $isAllowed;
                $childParentDenied = $explicitDeny;
            } else {
                $childParentGranted = $parentGrantedAll;
                $childParentDenied = $parentDenied;
            }

            if (!isset($node['children']) || !\is_array($node['children'])) {
                continue;
            }

            $this->walk($node['children'], $groupPerms, $childParentGranted, $childParentDenied, $result);
        }
    }
}
