<?php

/**
 * Beispiel-Klasse zu Demonstrationszwecken.
 *
 * Path: src/Example.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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
