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
 * @copyright (c) 2026 Felix Maywald. All rights reserved.
 * @license   https://github.com/RaptorXilef/kga-einfahrgenehmigung/blob/main/LICENSE
 *
 * @link      https://github.com/RaptorXilef/kga-einfahrgenehmigung/
 *
 * @author    Felix Maywald (@RaptorXilef)
 *
 * @since     0.1.0
 * - feat(arch): Initialer Aufbau der OOP-Struktur basierend auf Referenz-Projekt.
 * - feat(storage): Vorbereitung für Interface-basierte Speicherlogik (JSON/MySQL).
 * - feat(arch): Registrierung von MailServiceInterface und StorageInterface.
 */

declare(strict_types=1);

namespace App\Bootstrap;

use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Storage\StorageInterface;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Mail\SmtpMailService;
use App\Infrastructure\Storage\JsonStorage;

class Container
{
    private array $services = [];
    private array $instances = [];

    public function __construct(private readonly Config $config)
    {
        $this->setup();
    }

    private function setup(): void
    {
        // Konfiguration direkt verfügbar machen
        $this->instances[Config::class] = $this->config;

        // Storage Engine (JSON Pfad aus Config)
        $this->services[StorageInterface::class] = fn () => new JsonStorage(
            $this->config->get('storage_path', 'daten.json')
        );

        // Mail Service (Nutzt den Config-Service)
        $this->services[MailServiceInterface::class] = fn () => new SmtpMailService(
            $this->get(Config::class)
        );
    }

    public function get(string $id): object
    {
        if (!isset($this->instances[$id])) {
            $this->instances[$id] = ($this->services[$id])();
        }
        return $this->instances[$id];
    }
}
