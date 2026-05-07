<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Service Container (Dependency Injector).
 *
 * Zentraler Bootstrapping-Punkt der Anwendung. Erstellt Instanzen
 * und verwaltet Abhängigkeiten (DI).
 *
 * Path:      src/Bootstrap/Container.php
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
use App\Core\Service\MigrationService;
use App\Core\Service\PermitService;
use App\Core\Service\VoucherService; // NEU
use App\Infrastructure\Auth\AuthService;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Mail\SmtpMailService;
use App\Infrastructure\Payment\PayPalService;
use App\Infrastructure\Storage\JsonStorage;
use App\Infrastructure\Storage\MySqlStorage;
use PDO;

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
        $this->instances[ConfigInterface::class] = $this->config;

        $this->registerInfrastructure();
        $this->registerCoreServices();
        $this->registerControllers();
    }

    private function registerInfrastructure(): void
    {
        // 1. Zentrale PDO Verbindung (Jetzt intelligent & blitzschnell)
        $this->services[\PDO::class] = function (): ?\PDO {
            $storageCfg = $this->config->get('storage_config', []);

            // SCHNELL-CHECK: Wird MySQL überhaupt irgendwo benötigt?
            $needsMysql = false;
            foreach ($storageCfg as $area) {
                if (($area['type'] ?? 'json') === 'mysql') {
                    $needsMysql = true;

                    break;
                }
            }

            // Wenn kein Service MySQL will -> Sofort NULL zurückgeben ohne zu warten!
            if (! $needsMysql) {
                return null;
            }

            // Nur wenn MySQL wirklich konfiguriert ist, versuchen wir zu verbinden
            $db  = $this->config->get('database');
            $dsn = "mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}";

            try {
                return new \PDO($dsn, $db['user'], $db['pass'], [
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES   => false,
                    // ZUSATZ-SICHERHEIT: Timeout auf 2 Sekunden begrenzen
                    \PDO::ATTR_TIMEOUT => 2,
                ]);
            } catch (\PDOException $e) {
                return null;
            }
        };

        $this->services[StorageInterface::class] = function (): MySqlStorage|JsonStorage {
            $mapping = $this->config->get('storage_config')['permits'] ?? ['type' => 'json'];

            if ($mapping['type'] === 'mysql') {
                $pdo = $this->get(\PDO::class);
                if (! $pdo) {
                    throw new \RuntimeException('Datenbank benötigt aber MySQL-Server ist offline.');
                }

                return new MySqlStorage($pdo);
            }

            // FIX: Dynamische Pfad-Ermittlung aus der Config
            $fileName = $mapping['file'] ?? 'permits_active.json';
            $path     = $this->config->get('root_path') . '/' .
                    $this->config->get('storage_path_prefix') .
                    $fileName;

            return new JsonStorage($path);
        };

        $this->services[MailServiceInterface::class] = fn (): SmtpMailService => new SmtpMailService(
            $this->get(ConfigInterface::class),
            $this->get(\PDO::class),
        );

        // PayPal Service (Nutzt das Config-Interface)
        $this->services[PaymentProviderInterface::class] = fn (): PayPalService => new PayPalService(
            $this->get(ConfigInterface::class),
        );

        // AuthService registrieren
        $this->services[AuthService::class] = fn (): AuthService => new AuthService(
            $this->get(Config::class),
            $this->get(\PDO::class),
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
            $this->get(\PDO::class),
        );

        // Service verwaltet die temporären Token für den Login
        $this->services[MagicLinkService::class] = fn (): MagicLinkService => new MagicLinkService(
            $this->get(ConfigInterface::class),
            $this->get(\PDO::class),
        );

        // FIX P1005: Jetzt mit 7 Argumenten (PDO am Ende hinzugefügt)
        $this->services[PermitService::class] = fn (): PermitService => new PermitService(
            $this->get(StorageInterface::class),
            $this->get(MailServiceInterface::class),
            $this->get(ConfigInterface::class),
            $this->get(HolidayService::class),
            $this->get(PaymentProviderInterface::class),
            $this->get(VoucherService::class),
            $this->get(\PDO::class),
        );

        // NEU: Migration Service
        $this->services[MigrationService::class] = fn (): MigrationService => new MigrationService(
            $this->get(ConfigInterface::class),
            $this->get(\PDO::class),
            $this->get(PermitService::class),
            $this->get(AuthService::class),
            $this->get(VoucherService::class),
            $this->get(MagicLinkService::class),
            $this->get(MailServiceInterface::class),
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
            $this->get(MigrationService::class),
            $this->get(MailServiceInterface::class),
        );

        // User Controller
        $this->services[UserController::class] = fn (): UserController => new UserController(
            $this->get(ConfigInterface::class),
            $this->get(AuthService::class),
        );

        // CheckController benötigt jetzt den HolidayService für die Live-Prüfung
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

        // History Controller für Pächter-Verlauf
        $this->services[HistoryController::class] = fn (): HistoryController => new HistoryController(
            $this->get(ConfigInterface::class),
            $this->get(PermitService::class),
            $this->get(MagicLinkService::class),
            $this->get(MailServiceInterface::class),
        );
    }

    public function get(string $id): mixed
    {
        if (! isset($this->instances[$id])) {
            $this->instances[$id] = ($this->services[$id])();
        }

        return $this->instances[$id];
    }
}
