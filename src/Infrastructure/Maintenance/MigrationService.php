<?php

declare(strict_types=1);

namespace App\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Maintenance\MigrationServiceInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Infrastructure\Mail\SmtpMailService;
use App\Infrastructure\Storage\MySqlAuditLogRepository;
use App\Infrastructure\Storage\MySqlCancelledPermitRepository;
use App\Infrastructure\Storage\MySqlLoginAttemptRepository;
use App\Infrastructure\Storage\MySqlMagicLinkRepository;
use App\Infrastructure\Storage\MySqlMailQueueRepository;
use App\Infrastructure\Storage\MySqlPermitArchiveRepository;
use App\Infrastructure\Storage\MySqlRoleRepository;
use App\Infrastructure\Storage\MySqlStorage;
use App\Infrastructure\Storage\MySqlUserRepository;
use App\Infrastructure\Storage\MySqlVerificationRepository;
use App\Infrastructure\Storage\MySqlVoucherRepository;
use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Service für automatisierte Datensicherungen (Backups) und System-Recovery.
 */
final readonly class MigrationService implements MigrationServiceInterface
{
    public function __construct(
        private ?PDO $pdo,
        private BackupService $backupService,
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    public function restore(string $timestamp, string $target, string $engine = 'all'): string
    {
        try {
            $this->backupService->createBackup($target . '_before_restore');
        } catch (Exception $e) {
            return 'Abbruch: Sicherheits-Backup des Ist-Zustands konnte nicht erstellt werden (' . $e->getMessage() . ').';
        }

        $data = $this->backupService->getBackupData($timestamp, $target);

        if ($data === null) {
            return "Fehler: Keine gültige Backup-Datei für '$target' im Ordner $timestamp gefunden.";
        }

        if ($this->pdo instanceof PDO) {
            $this->saveToSql($target, $data);

            return "Erfolg: '$target' wurde aus Backup [$timestamp] in MySQL wiederhergestellt.";
        }

        return 'Hinweis: Es wurden keine Daten wiederhergestellt (Datenbank nicht erreichbar).';
    }

    public function clearCache(): string
    {
        $root = $this->config->get('root_path');
        $deptracCache = $root . '/.cache/deptrac/.deptrac.cache';
        if (\file_exists($deptracCache)) {
            @\unlink($deptracCache);
        }

        return 'Erfolg: Der System-Cache wurde geleert und die Berechtigungen neu kompiliert.';
    }

    public function truncateTarget(string $target, string $engine = 'all'): string
    {
        try {
            $this->backupService->createBackup($target . '_before_truncate');
        } catch (Exception $e) {
            return 'Abbruch: Sicherheits-Backup konnte nicht erstellt werden (' . $e->getMessage() . ').';
        }

        $cfg = $this->config->get('storage_config')[$target] ?? null;
        if (!$cfg || !$this->pdo instanceof PDO) {
            return "Fehler: Unbekannter Speicherbereich '$target' oder DB offline.";
        }

        try {
            $tableName = $cfg['table'];
            $allowedTables = \array_column($this->config->get('storage_config'), 'table');

            if (!\in_array($tableName, $allowedTables, true)) {
                throw new RuntimeException('Sicherheitsabbruch: Tabellenname nicht in Config autorisiert.');
            }

            $this->pdo->exec("TRUNCATE TABLE `$tableName`");

            return "Erfolg: Die Tabelle '$tableName' wurde geleert. Ein Backup wurde erstellt.";
        } catch (PDOException $e) {
            \error_log('Truncate Error MySQL: ' . $e->getMessage());

            return 'Fehler beim Leeren der Tabelle: ' . $e->getMessage();
        }
    }

    private function saveToSql(string $key, array $data): void
    {
        if (!$this->pdo instanceof PDO) {
            return;
        }

        match ($key) {
            'audit_logs' => (new MySqlAuditLogRepository($this->pdo, $this->config))->import($data),
            'roles' => (new MySqlRoleRepository($this->pdo, $this->config, $this->jsonHelper))->import($data),
            'users' => (new MySqlUserRepository($this->pdo, $this->config))->import($data),
            'login_attempts' => (new MySqlLoginAttemptRepository($this->pdo, $this->config))->import($data),
            'magic_links' => (new MySqlMagicLinkRepository($this->pdo, $this->config))->import($data),
            'mail_log' => (new SmtpMailService($this->pdo, $this->config, $this->jsonHelper))->importLogs($data, true),
            'mail_queue' => (new MySqlMailQueueRepository($this->pdo, $this->config, $this->jsonHelper))->import($data),
            'pending_verification' => (new MySqlVerificationRepository($this->pdo, $this->config, $this->jsonHelper))->savePending($data, true),
            'permits' => (new MySqlStorage($this->pdo, $this->jsonHelper))->import($data),
            'permits_archive' => (new MySqlPermitArchiveRepository($this->pdo, $this->config, $this->jsonHelper))->import($data),
            'permits_cancelled' => (new MySqlCancelledPermitRepository($this->pdo, $this->config, $this->jsonHelper))->import($data),
            'verified_pending' => (new MySqlVerificationRepository($this->pdo, $this->config, $this->jsonHelper))->saveVerified($data, true),
            'vouchers' => (new MySqlVoucherRepository($this->pdo, $this->config, $this->jsonHelper))->saveAll($data, true),
            'vouchers_archive' => (new MySqlVoucherRepository($this->pdo, $this->config, $this->jsonHelper))->importArchive($data),
            default => throw new InvalidArgumentException("Kein SQL-Mapper für Speicherbereich '$key' definiert.")
        };
    }
}
