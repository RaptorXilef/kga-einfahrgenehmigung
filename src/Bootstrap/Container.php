<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Service Container (Dependency Injector). v0.9.4
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
 */

declare(strict_types=1);

namespace App\Bootstrap;

use App\Contracts\Config\ConfigInterface; // NEU
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Service\HolidayService;
use App\Core\Service\PermitService;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Mail\SmtpMailService;
use App\Infrastructure\Payment\PayPalService;
use App\Infrastructure\Storage\JsonStorage;

/**
 * Service Container (Dependency Injector)
 */
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
        // Wir registrieren die Config sowohl unter ihrer Klasse als auch unter dem Interface
        $this->instances[Config::class]          = $this->config;
        $this->instances[ConfigInterface::class] = $this->config; // WICHTIG für Entkopplung

        // 2. Hilfs-Services (Stateless)
        $this->services[HolidayService::class] = fn (): HolidayService => new HolidayService();

        // 3. Infrastruktur-Services
        $this->services[StorageInterface::class] = function (): JsonStorage {
            $root         = $this->config->get('root_path');
            $path         = $this->config->get('storage_path', 'storage/daten.json');
            $absolutePath = \str_starts_with($path, '/') ? $path : $root . '/' . $path;

            return new JsonStorage($absolutePath);
        };

        // Mail Service (Nutzt das Config-Interface)
        $this->services[MailServiceInterface::class] = fn (): SmtpMailService => new SmtpMailService(
            $this->get(ConfigInterface::class),
        );

        // PayPal Service (Nutzt das Config-Interface)
        $this->services[PaymentProviderInterface::class] = fn (): PayPalService => new PayPalService(
            $this->get(ConfigInterface::class),
        );

        // 4. Kern-Logik (Orchestratoren)
        $this->services[PermitService::class] = fn (): PermitService => new PermitService(
            $this->get(StorageInterface::class),
            $this->get(MailServiceInterface::class),
            $this->get(ConfigInterface::class), // Nutzt jetzt das Interface!
            $this->get(HolidayService::class),
            $this->get(PaymentProviderInterface::class),
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
