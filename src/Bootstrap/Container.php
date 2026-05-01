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
 */

declare(strict_types=1);

namespace App\Bootstrap;

use App\Application\AdminController;
use App\Application\CheckController;
use App\Application\HistoryController;
use App\Application\PaymentController;
use App\Application\PermitController;
use App\Application\UserController;
use App\Application\VerificationController;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Service\HolidayService;
use App\Core\Service\MagicLinkService;
use App\Core\Service\PermitService;
use App\Core\Service\VoucherService;
use App\Infrastructure\Auth\AuthService;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Mail\SmtpMailService;
use App\Infrastructure\Payment\PayPalService;
use App\Infrastructure\Storage\JsonStorage;

/**
 * Service Container (Dependency Injector) v0.10.4.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class Container
{
    /**
     * @var array<string, \Closure>
     */
    private array $services = [];

    /**
     * @var array<string, object>
     */
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

        $this->registerInfrastructure();
        $this->registerCoreServices();
        $this->registerControllers();
    }

    private function registerInfrastructure(): void
    {
        // --- INFRASTRUKTUR (Storage, Mail, Payment, Auth) ---
        $this->services[StorageInterface::class] = function (): JsonStorage {
            $root = (string) $this->config->get('root_path');
            $path = (string) $this->config->get('storage_path', 'storage/daten.json');

            return new JsonStorage(\str_starts_with($path, '/') ? $path : $root . '/' . $path);
        };

        // Mail Service (Nutzt das Config-Interface)
        $this->services[MailServiceInterface::class] = fn (): SmtpMailService => new SmtpMailService(
            $this->get(ConfigInterface::class),
        );

        // PayPal Service (Nutzt das Config-Interface)
        $this->services[PaymentProviderInterface::class] = fn (): PayPalService => new PayPalService(
            $this->get(ConfigInterface::class),
        );

        // AuthService registrieren
        $this->services[AuthService::class] = fn (): AuthService => new AuthService(
            $this->get(Config::class),
        );
    }

    private function registerCoreServices(): void
    {
        // HolidayService benötigt jetzt das Config-Interface
        $this->services[HolidayService::class] = fn (): HolidayService => new HolidayService(
            $this->get(ConfigInterface::class),
        );

        // Service für Gutscheine
        $this->services[VoucherService::class] = fn (): VoucherService => new VoucherService(
            $this->get(ConfigInterface::class),
        );

        // Service verwaltet die temporären Token für den Login
        $this->services[MagicLinkService::class] = fn (): MagicLinkService => new MagicLinkService(
            $this->get(ConfigInterface::class),
        );

        // --- CORE LOGIC (Orchestratoren) ---
        $this->services[PermitService::class] = fn (): PermitService => new PermitService(
            $this->get(StorageInterface::class),
            $this->get(MailServiceInterface::class),
            $this->get(ConfigInterface::class),
            $this->get(HolidayService::class),
            $this->get(PaymentProviderInterface::class),
            $this->get(VoucherService::class),
        );
    }

    private function registerControllers(): void
    {
        // Admin Controller
        $this->services[AdminController::class] = fn (): AdminController => new AdminController(
            $this->get(ConfigInterface::class),
            $this->get(AuthService::class),
            $this->get(StorageInterface::class),
            $this->get(PermitService::class),
        );

        // User Controller (Neu für v0.9.7)
        $this->services[UserController::class] = fn (): UserController => new UserController(
            $this->get(ConfigInterface::class),
            $this->get(AuthService::class),
        );

        // FIX: CheckController benötigt jetzt den HolidayService für die Live-Prüfung
        $this->services[CheckController::class] = fn (): CheckController => new CheckController(
            $this->get(ConfigInterface::class),
            $this->get(StorageInterface::class),
            $this->get(AuthService::class),
            $this->get(HolidayService::class),
            $this->get(PermitService::class),
        );

        // PermitController für index.php
        $this->services[PermitController::class] = fn (): PermitController => new PermitController(
            $this->get(ConfigInterface::class),
            $this->get(PermitService::class),
        );

        $this->services[VerificationController::class] = fn (): VerificationController => new VerificationController(
            $this->get(ConfigInterface::class),
            $this->get(PermitService::class),
        );

        $this->services[PaymentController::class] = fn (): PaymentController => new PaymentController(
            $this->get(PermitService::class),
            $this->get(ConfigInterface::class),
        );

        // History Controller für Pächter-Verlauf NEU mit v0.13.0:
        $this->services[HistoryController::class] = fn (): HistoryController => new HistoryController(
            $this->get(ConfigInterface::class),
            $this->get(PermitService::class),
            $this->get(MagicLinkService::class),
            $this->get(MailServiceInterface::class),
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
