<?php

declare(strict_types=1);

namespace App\Contracts\Event;

/**
 * Interface für den Event Dispatcher.
 * Leitet aufgetretene Ereignisse (Events) an die registrierten Lauscher (Listeners) weiter.
 *
 * Path: src/Contracts/Event/EventDispatcherInterface.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
interface EventDispatcherInterface
{
    /**
     * Verteilt ein Event an alle registrierten Listener.
     *
     * @param object $event Das Event-Objekt (z.B. PermitCreated).
     */
    public function dispatch(object $event): void;

    /**
     * Registriert einen neuen Listener für ein spezifisches Event.
     *
     * @param string   $eventClass Der vollqualifizierte Klassenname des Events.
     * @param callable $listener   Die Funktion/Klasse, die aufgerufen werden soll.
     */
    public function addListener(string $eventClass, callable $listener): void;
}
