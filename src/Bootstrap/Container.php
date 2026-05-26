<?php

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
use App\Core\Service\MailQueueService;
use App\Core\Service\MigrationService;
use App\Core\Service\PermitService;
use App\Core\Service\VoucherService;
use App\Infrastructure\Auth\AuthService;
use App\Infrastructure\Config\Config;
use App\Infrastructure\Mail\SmtpMailService;
use App\Infrastructure\Payment\PayPalService;
use App\Infrastructure\Storage\JsonStorage;
use App\Infrastructure\Storage\MySqlStorage;

/**
 * Dependency Injection (DI) Container der Anwendung.
 *
 * Verwaltet den Objekt-Lifecycle durch Lazy Loading. Registriert Infrastruktur-Komponenten,
 * Core-Services und Controller und injiziert benötigte Abhängigkeiten.
 * Kontext: Zentraler Registry- und Inversion-of-Control (IoC) Knotenpunkt der Applikation.
 *
 * Path: src/Bootstrap/Container.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 *
 * @copyright (c) 2026 Felix Maywald. All rights reserved.
 * @license   https://github.com/RaptorXilef/kga-einfahrgenehmigung/blob/main/LICENSE
 *
 * @link      https://github.com/RaptorXilef/kga-einfahrgenehmigung/
 *
 * @author    Felix Maywald (@RaptorXilef)
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

    /**
     * Initialisiert den internen Registrierungsprozess.
     * Mappt die Konfigurations-Instanzen und stößt die funktionsspezifischen Register-Methoden an.
     */
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

    /**
     * Registriert technologische Kernkomponenten und Basis-Schnittstellen.
     * Definiert die Factory-Closures für die PDO-Datenbankverbindung, Storage-Engines
     * (Wechsel zwischen MySQL und JSON), Mail-Services und Zahlungsanbieter.
     */
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
            $db        = $this->config->get('database');
            $dsnWithDb = "mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}";
            $pdo       = null;

            try {
                // Normaler Verbindungsversuch
                $pdo = new \PDO($dsnWithDb, $db['user'], $db['pass'], [
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES   => false,
                    \PDO::ATTR_TIMEOUT            => 2,
                ]);
            } catch (\PDOException $e) {
                // 1049 = Unknown database (Datenbank existiert nicht)
                if ($e->getCode() != 1049) {
                    \error_log('MySQL Connection Error: ' . $e->getMessage());

                    return null;
                }

                $dsnWithoutDb = "mysql:host={$db['host']};charset={$db['charset']}";

                try {
                    // Verbinden OHNE Datenbanknamen
                    $pdo = new \PDO($dsnWithoutDb, $db['user'], $db['pass'], [
                        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                        \PDO::ATTR_EMULATE_PREPARES   => false,
                        \PDO::ATTR_TIMEOUT            => 2,
                    ]);
                    // Datenbank anlegen und anwählen
                    $sql = "CREATE DATABASE IF NOT EXISTS `{$db['dbname']}` " .
                        "CHARACTER SET {$db['charset']} COLLATE {$db['charset']}_unicode_ci";

                    $pdo->exec($sql);
                    $pdo->exec("USE `{$db['dbname']}`");
                } catch (\PDOException $e2) {
                    \error_log('MySQL Auto-Install Error (DB Create): ' . $e2->getMessage());

                    return null;
                }
            }

            // High-Performance Tabellen-Check (kostet <0.1ms) & Auto-Setup
            try {
                $pdo->query('SELECT 1 FROM `users` LIMIT 1');
            } catch (\PDOException) {
                // Tabelle existiert nicht -> Es ist ein frisches System! Schema ausrollen!
                $schema = $this->config->get('db_schema', []);
                foreach ($schema as $tableName => $sql) {
                    try {
                        $pdo->exec($sql);
                    } catch (\PDOException $ex) {
                        \error_log("MySQL Auto-Install Error (Table $tableName): " . $ex->getMessage());
                    }
                }
            }

            return $pdo;
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

        // 1. Der echte SMTP-Versender (umbenannt, damit wir ihn intern nutzen können)
        $this->services['mail.smtp'] = fn (): SmtpMailService => new SmtpMailService(
            $this->get(ConfigInterface::class),
            $this->get(\PDO::class),
        );

        // 2. Das offizielle Interface zeigt nun auf die Queue!
        $this->services[MailServiceInterface::class] = fn (): MailQueueService => new MailQueueService(
            $this->get(ConfigInterface::class),
            $this->get(\PDO::class),
            $this->get('mail.smtp'),
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

    /**
     * Registriert die fachlichen Kern-Dienste (Domain Business Logik).
     * Mappt Services für Kalenderdaten, Gutscheine, Magic-Links, Antragslogiken und Systemmigrationen.
     */
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

    /**
     * Registriert sämtliche Application-Controller im Container.
     * Bereitet die HTTP-Einstiegspunkte mit ihren jeweiligen Service-Abhängigkeiten für das Routing vor.
     */
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
            $this->get(HolidayService::class),
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
            $this->get(HolidayService::class),
        );
    }

    /**
     * Löst eine Abhängigkeit auf und liefert eine shared (Singleton) Instanz zurück.
     * Erstellt das Objekt beim ersten Aufruf über die hinterlegte Closure (Lazy Loading)
     * und cached es für nachfolgende Zugriffe im System.
     *
     * @param string $id Die vollqualifizierte Klasse oder der Identifikations-String des Services.
     *
     * @return mixed Die instanziierte Service- oder Controller-Komponente.
     */
    public function get(string $id): mixed
    {
        if (! isset($this->instances[$id])) {
            $this->instances[$id] = ($this->services[$id])();
        }

        return $this->instances[$id];
    }
}
