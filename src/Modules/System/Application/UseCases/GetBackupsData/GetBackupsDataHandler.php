<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetBackupsData;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\System\AssetHelperInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use Override;

/**
 * Bereitet die Backup-Liste sowie die möglichen Tabellen-Ziele logikfrei für die View auf.
 *
 * @implements QueryHandlerInterface<GetBackupsDataQuery, BackupsResultDto>
 */
final readonly class GetBackupsDataHandler implements QueryHandlerInterface
{
    private const array TARGET_LABELS = [
        'all' => 'Vollständige Datenbank (Alle Tabellen)',
        'permits' => 'Aktive Genehmigungen (permits)',
        'permits_archive' => 'Genehmigungs-Archiv (permits_archive)',
        'permits_cancelled' => 'Stornierte Genehmigungen (permits_cancelled)',
        'vouchers' => 'Aktive Gutscheine (vouchers)',
        'vouchers_archive' => 'Gutschein-Archiv (vouchers_archive)',
        'users' => 'Benutzerkonten (users)',
        'roles' => 'Rollen & Rechte (roles)',
        'audit_logs' => 'Audit-Log (audit_logs)',
        'mail_log' => 'E-Mail Versand-Log (mail_logs)',
        'mail_queue' => 'E-Mail Warteschlange (mail_queue)',
        'login_attempts' => 'Login-Sperren / Rate-Limits (login_attempts)',
    ];

    public function __construct(
        private BackupServiceInterface $backupService,
        private ConfigInterface $config,
        private AssetHelperInterface $assetHelper,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param GetBackupsDataQuery $query
     */
    #[Override]
    public function handle(mixed $query): BackupsResultDto
    {
        $backupSettings = $this->config->getArray('backup_settings');
        $ftpEnabled = (bool) ($backupSettings['ftp']['enabled'] ?? false);

        $storageConfig = $this->config->getArray('storage_config');
        $targetOptions = [
            ['value' => 'all', 'label' => self::TARGET_LABELS['all']],
        ];

        foreach ($storageConfig as $key => $cfg) {
            if (!isset($cfg['table'])) {
                continue;
            }
            $label = self::TARGET_LABELS[$key] ?? "Tabelle: {$cfg['table']} ({$key})";
            $targetOptions[] = [
                'value' => (string) $key,
                'label' => $label,
            ];
        }

        $rawBackups = $this->backupService->listBackups();
        $items = [];

        foreach ($rawBackups as $b) {
            $timestamp = isset($b['date']) && \is_numeric($b['date']) ? (int) $b['date'] : $this->clock->now()->getTimestamp();
            $dateFormatted = $this->clock->now()->setTimestamp($timestamp)->format('d.m.Y H:i') . ' Uhr';

            $sizeBytes = isset($b['size']) && \is_numeric($b['size']) ? (int) $b['size'] : 0;
            $sizeMb = \number_format($sizeBytes / 1024 / 1024, 2, ',', '.');

            $targetKey = (string) ($b['target'] ?? 'all');
            $targetLabel = $targetKey === 'all' ? 'Komplett-Backup' : (self::TARGET_LABELS[$targetKey] ?? $targetKey);
            $iconFile = $targetKey === 'all' ? 'package.webp' : 'document.webp';

            $tables = [];
            if (isset($b['tables']) && \is_array($b['tables'])) {
                foreach ($b['tables'] as $tbl) {
                    $tables[] = (string) $tbl;
                }
            }

            $items[] = new BackupItemViewDto(
                filename: (string) ($b['filename'] ?? ''),
                sizeMb: $sizeMb,
                dateFormatted: $dateFormatted,
                targetLabel: $targetLabel,
                targetIconUrl: $this->assetHelper->url('assets/img/icons/' . $iconFile),
                tables: $tables,
            );
        }

        return new BackupsResultDto(
            ftpEnabled: $ftpEnabled,
            targetOptions: $targetOptions,
            items: $items,
        );
    }
}
