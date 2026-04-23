<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Service Container (Dependency Injector).
 *
 * Zentraler Bootstrapping-Punkt der Anwendung. Erstellt Instanzen
 * und verwaltet Abhängigkeiten (DI).
 *
 * @file      bootstrap/container.php
 *
 * @since     0.1.0
 * - feat(arch): Initialer Aufbau der OOP-Struktur basierend auf Referenz-Projekt.
 * - feat(storage): Vorbereitung für Interface-basierte Speicherlogik (JSON/MySQL).
 */

declare(strict_types=1);

namespace App\Bootstrap;

use App\Storage\JsonStorage;
use App\Storage\StorageInterface;
use App\Service\MailService;

// Simpler Container-Ansatz für das Miniprojekt
class Container
{
    private array $services = [];
    private array $instances = [];

    public function __construct(private array $config)
    {
        $this->setup();
    }

    private function setup(): void
    {
        // Storage Engine (Wählbar via Config)
        $this->services[StorageInterface::class] = fn () => new JsonStorage($this->config['storage']['json_path']);

        // Mail Service
        $this->services[MailService::class] = fn () => new MailService($this->config['mail']);
    }

    public function get(string $id): object
    {
        if (!isset($this->instances[$id])) {
            $this->instances[$id] = ($this->services[$id])();
        }
        return $this->instances[$id];
    }
}
