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
            'all' => 'Voll-Backup (Alle Tabellen)',
            'permits' => 'Aktive Genehmigungen (permits)',
            'permits_archive' => 'Archivierte Genehmigungen (permits_archive)',
            'permits_cancelled' => 'Stornierte Genehmigungen (permits_cancelled)',
            'vouchers' => 'Gutscheincodes (vouchers)',
            'vouchers_archive' => 'Gutschein-Archiv (vouchers_archive)',
            'users' => 'Benutzerkonten (users)',
            'roles' => 'Rechte-Rollen (roles)',
            'audit_logs' => 'Audit-Logs (audit_logs)',
            'mail_log' => 'E-Mail-Logs (mail_logs)',
            'mail_queue' => 'E-Mail-Warteschlange (mail_queue)',
        ];

        $storageConfig = $this->config->getArray('storage_config');
        $targetOptions = [
            ['value' => 'all', 'label' => $targetLabels['all']],
        ];

        foreach (\array_keys($storageConfig) as $key) {
            $targetOptions[] = [
                'value' => (string) $key,
                'label' => $targetLabels[$key] ?? (string) $key,
            ];
        }

        $rawBackups = $this->backupService->listBackups();
        $items = [];

        foreach ($rawBackups as $b) {
            $timestamp = (int) ($b['date'] ?? 0);
            $dt = $this->clock->now()->setTimestamp($timestamp);
            $sizeBytes = (int) ($b['size'] ?? 0);
            $sizeMb = \number_format($sizeBytes / 1024 / 1024, 2, ',', '.');
            $targetKey = (string) ($b['target'] ?? 'all');

            $items[] = new BackupItemViewDto(
                filename: (string) ($b['filename'] ?? ''),
                sizeMb: $sizeMb,
                dateFormatted: $dt->format('d.m.Y H:i') . ' Uhr',
                targetLabel: $targetLabels[$targetKey] ?? $targetKey,
                targetIconUrl: $this->assetHelper->url('assets/img/icons/' . ($targetKey === 'all' ? 'package.webp' : 'document.webp')),
                tables: \is_array($b['tables'] ?? null) ? $b['tables'] : [],
            );
        }

        return new BackupsResultDto(
            ftpEnabled: $ftpEnabled,
            targetOptions: $targetOptions,
            items: $items,
        );
    }
}
