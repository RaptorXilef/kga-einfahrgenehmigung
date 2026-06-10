<?php

/**
 * Beispiel-Klasse zu Demonstrationszwecken.
 *
 * Path: src/Example.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

namespace App;

/**
 * Dummy-Klasse für mathematische Basis-Operationen.
 */
class Example
{
    /**
     * Addiert zwei Ganzzahlen miteinander.
     *
     * @param int $var_a Der erste Summand.
     * @param int $var_b Der zweite Summand.
     *
     * @return int Die Summe beider Zahlen.
     */
    public function add(int $var_a, int $var_b): int
    {
        return $var_a + $var_b;
    }
}
