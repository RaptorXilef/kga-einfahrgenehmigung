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
 * Bereitet die vorhandenen Backups und Tabellen-Ziele logikfrei für die Dashboard-View auf.
 *
 * @implements QueryHandlerInterface<GetBackupsDataQuery, BackupsResultDto>
 */
final readonly class GetBackupsDataHandler implements QueryHandlerInterface
{
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
        $backupCfg = $this->config->getArray('backup_settings');
        $ftpEnabled = (bool) ($backupCfg['ftp']['enabled'] ?? false);

        $targetLabels = [
            'all' => 'Komplettes System (Alle Tabellen)',
            'permits' => 'Aktive Genehmigungen (permits)',
            'permits_archive' => 'Genehmigungs-Archiv (permits_archive)',
            'permits_cancelled' => 'Stornierte Genehmigungen (permits_cancelled)',
            'users' => 'Benutzerkonten (users)',
            'roles' => 'Rollen & Rechte (roles)',
            'vouchers' => 'Aktive Gutscheine (vouchers)',
            'vouchers_archive' => 'Gutschein-Archiv (vouchers_archive)',
            'mail_log' => 'E-Mail-Versandprotokoll (mail_logs)',
            'mail_queue' => 'E-Mail-Warteschlange (mail_queue)',
            'audit_logs' => 'Sicherheits-Audit-Log (audit_logs)',
        ];

        $storageConfig = $this->config->getArray('storage_config');
        $targetOptions = [
            ['value' => 'all', 'label' => $targetLabels['all']],
        ];

        foreach ($storageConfig as $key => $cfg) {
            if (!\is_array($cfg) || !isset($cfg['table'])) {
                continue;
            }
            $label = $targetLabels[$key] ?? \sprintf('%s (%s)', $key, (string) $cfg['table']);
            $targetOptions[] = [
                'value' => (string) $key,
                'label' => $label,
            ];
        }

        $rawBackups = $this->backupService->listBackups();
        $items = [];

        foreach ($rawBackups as $raw) {
            $timestamp = (int) ($raw['date'] ?? 0);
            $dateFormatted = $this->clock->now()->setTimestamp($timestamp)->format('d.m.Y H:i') . ' Uhr';
            $sizeBytes = (int) ($raw['size'] ?? 0);
            $sizeMb = \number_format($sizeBytes / 1024 / 1024, 2, ',', '.');
            $targetKey = (string) ($raw['target'] ?? 'all');

            $isFull = $targetKey === 'all';
            $iconFile = $isFull ? 'package.webp' : 'document.webp';

            $tables = [];
            if (isset($raw['tables']) && \is_array($raw['tables'])) {
                foreach ($raw['tables'] as $tbl) {
                    $tables[] = (string) $tbl;
                }
            }

            $items[] = new BackupItemViewDto(
                filename: (string) ($raw['filename'] ?? ''),
                dateFormatted: $dateFormatted,
                sizeMb: $sizeMb,
                targetLabel: $isFull ? 'Voll-Backup' : $targetKey,
                targetIconUrl: $this->assetHelper->url('assets/img/icons/' . $iconFile),
                tables: $tables,
            );
        }

        return new BackupsResultDto(
            items: $items,
            targetOptions: $targetOptions,
            ftpEnabled: $ftpEnabled,
        );
    }
}
