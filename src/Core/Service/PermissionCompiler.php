<?php

declare(strict_types=1);

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

// Path: src/Core/Service/PermissionCompiler.php

namespace App\Core\Service;

final class PermissionCompiler
{
    /**
     * Wandelt den Baum in ein flaches Array um, basierend auf den Gruppen-Einstellungen.
     */
    public function compile(array $structure, array $groupPermissions): array
    {
        $flat = [];
        $this->walk($structure, $groupPermissions, true, $flat);

        return $flat;
    }

    private function walk(array $nodes, array $groupPerms, bool $parentAllowed, array &$result): void
    {
        foreach ($nodes as $node) {
            // Falls kein Key da ist, überspringen wir die Prüfung für diesen Knoten
            $key = $node['key'] ?? null;

            if ($key !== null) {

                // Ein Recht ist nur erlaubt, wenn:
                // 1. Der Vater erlaubt ist
                // 2. Es explizit in der Gruppe steht ODER die Gruppe den Master '*' hat
                // 3. Es NICHT explizit verboten ist ('-key')

                $explicitAllow = \in_array($key, $groupPerms, true) || \in_array('*', $groupPerms, true);
                $explicitDeny  = \in_array('-' . $key, $groupPerms, true);

                // Cascading Deny Logik
                $isAllowed    = $parentAllowed && $explicitAllow && ! $explicitDeny;
                $result[$key] = $isAllowed;
            } else {
                // Wenn kein Key da ist (Kategorie), gilt der Zustand des Vaters für die Kinder.
                $isAllowed = $parentAllowed;
            }

            if (isset($node['children'])) {
                $this->walk($node['children'], $groupPerms, $isAllowed, $result);
            }
        }
    }
}
