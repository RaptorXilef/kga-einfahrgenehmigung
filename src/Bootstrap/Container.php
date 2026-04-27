<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Service Container (Dependency Injector).
 *
 * Zentraler Bootstrapping-Punkt der Anwendung. Erstellt Instanzen
 * und verwaltet Abhängigkeiten (DI).
 *
 * @file      src/Bootstrap/Container.php
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
 * @since     0.4.0 - refactor(arch): Injection von HolidayService hinzugefügt.
 */

declare(strict_types=1);

namespace App\Bootstrap;

use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Service\HolidayService;
use App\Core\Service\PermitService;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Mail\SmtpMailService;
use App\Infrastructure\Payment\PayPalService;
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
        // 1. Konfiguration
        $this->instances[Config::class] = $this->config;

        // 2. Hilfs-Services (Stateless)
        $this->services[HolidayService::class] = fn (): HolidayService => new HolidayService();

        // 3. Infrastruktur-Services
        $this->services[StorageInterface::class] = function (): JsonStorage {
            $root         = $this->config->get('root_path');
            $path         = $this->config->get('storage_path', 'storage/daten.json');
            $absolutePath = \str_starts_with($path, '/') ? $path : $root . '/' . $path;

            return new JsonStorage($absolutePath);
        };

        // Mail Service (Nutzt den Config-Service)
        $this->services[MailServiceInterface::class] = fn (): SmtpMailService => new SmtpMailService(
            $this->get(Config::class),
        );

        $this->services[PaymentProviderInterface::class] = fn (): PayPalService => new PayPalService(
            $this->get(Config::class),
        );

        // 4. Kern-Logik (Orchestratoren)
        $this->services[PermitService::class] = fn (): PermitService => new PermitService(
            $this->get(StorageInterface::class),
            $this->get(MailServiceInterface::class),
            $this->get(Config::class),
            $this->get(HolidayService::class),
            $this->get(PaymentProviderInterface::class), // NEU: Zahlungsanbieter hinzufügen
        );
    }

    public function get(string $id): object
    {
        if (! isset($this->instances[$id])) {
            $this->instances[$id] = ($this->services[$id])();
        }

        return $this->instances[$id];
    }
}
