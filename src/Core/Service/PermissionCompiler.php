<?php

declare(strict_types=1);

namespace App\Core\Service;

/**
 * TODO Phase 3 nicht nötig
 * Performanz-Compiler für verschachtelte RBAC (Role-Based Access Control) Berechtigungsbäume.
 *
 * Evaluiert verschachtelte Modulbäume gegen flache Gruppenrechte, unterstützt Wildcards ('*')
 * sowie explizite Verbote ('-') und kompiliert daraus eine flache Boolean-Lookup-Tabelle.
 * Kontext: Kernkomponente zur schnellen, O(1)-basierten Rechteprüfung in der Applikation.
 *
 * Path: src/Core/Service/PermissionCompiler.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final class PermissionCompiler
{
    /**
     * Kompiliert eine hierarchische Menü-/Rechtestruktur in eine flache Key-Value-Map.
     *
     * Wandelt den Permissions-Baum in ein flaches Array um, basierend auf den Gruppen-Einstellungen.
     *
     * @param array<int, array<string, mixed>> $structure        Der hierarchische Baum aus der permissions-Config.
     * @param array<int, string>               $groupPermissions Ungefilterte Berechtigungs-Strings der Benutzergruppe.
     *
     * @return array<string, bool> Flache Map, bei der Berechtigungs-Keys direkt auf True/False mappen.
     */
    public function compile(array $structure, array $groupPermissions): array
    {
        $flat = [];
        $this->walk($structure, $groupPermissions, true, $flat);

        return $flat;
    }

    /**
     * Rekursiver Tree-Walker zur Vererbung und Auswertung von Rechten über Knotenebenen hinweg.
     * Beachtet hierarchische Parent-Sperren, verarbeitet Wildcards und wertet Negierungen (Präfix '-') aus.
     *
     * @param array<int, array<string, mixed>> $nodes         Aktuelle Knoten-Ebene des Baums.
     * @param array<int, string>               $groupPerms    Die Rechte-Vorgaben der Gruppe.
     * @param bool                             $parentAllowed Vererbter Freigabestatus der übergeordneten Ebene.
     * @param array<string, bool>              $result        Referenzierte Ergebnisliste für den Output.
     *
     * @return void Modifiziert das Ergebnis-Array per Referenz.
     */
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

            if (! isset($node['children'])) {
                continue;
            }

            $this->walk($node['children'], $groupPerms, $isAllowed, $result);
        }
    }
}
