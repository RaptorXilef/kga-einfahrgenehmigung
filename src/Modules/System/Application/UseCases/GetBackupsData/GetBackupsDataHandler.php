<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetBackupsData;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\System\AssetHelperInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;

/**
 * @implements QueryHandlerInterface<GetBackupsDataQuery, BackupsResultDto>
 */
final readonly class GetBackupsDataHandler implements QueryHandlerInterface
{
    public function __construct(
        private BackupServiceInterface $backupService,
        private AssetHelperInterface $assetHelper,
        private ConfigInterface $config,
    ) {
    }

    /**
     * @param GetBackupsDataQuery $query
     */
    public function handle(mixed $query): BackupsResultDto
    {
        $backups = $this->backupService->listBackups();
        $dtos = [];

        $targets = [
            'all' => ['label' => 'Komplette Datenbank (Alle)', 'icon' => 'package.webp', 'emoji' => '📦'],
            'roles' => ['label' => 'Rechte-Rollen', 'icon' => 'shield.webp', 'emoji' => '🛡️'],
            'magic_links' => ['label' => 'Login-Tokens', 'icon' => 'link.webp', 'emoji' => '🔗'],
            'mail_log' => ['label' => 'E-Mail Protokolle', 'icon' => 'envelope.webp', 'emoji' => '📧'],
            'mail_queue' => ['label' => 'Mail-Warteschlange', 'icon' => 'inbox.webp', 'emoji' => '📤'],
            'pending_verification' => ['label' => 'Warteraum (E-Mail)', 'icon' => 'envelope.webp', 'emoji' => '✉️'],
            'permits' => ['label' => 'Genehmigungen', 'icon' => 'document.webp', 'emoji' => '📄'],
            'permits_archive' => ['label' => 'Genehmigungs-Archiv', 'icon' => 'archive.webp', 'emoji' => '🗄️'],
            'permits_cancelled' => ['label' => 'Stornierte Genehmigungen', 'icon' => 'blocked.webp', 'emoji' => '🚫'],
            'users' => ['label' => 'Benutzerkonten', 'icon' => 'user.webp', 'emoji' => '👥'],
            'verified_pending' => ['label' => 'Warteraum (Zahlung)', 'icon' => 'card.webp', 'emoji' => '💳'],
            'vouchers' => ['label' => 'Gutscheine', 'icon' => 'voucher.webp', 'emoji' => '🎟️'],
            'vouchers_archive' => ['label' => 'Gutschein-Archiv', 'icon' => 'archive.webp', 'emoji' => '📚'],
            'login_attempts' => ['label' => 'Login-Versuche', 'icon' => 'shield.webp', 'emoji' => '🛡'],
            'update_migrations' => ['label' => 'Update-Migration-Verlauf', 'icon' => 'sync.webp', 'emoji' => '♻'],
            'audit_logs' => ['label' => 'Nutzerprotokoll (Audit)', 'icon' => 'view.webp', 'emoji' => '👁️‍🗨️'],
        ];

        foreach ($backups as $b) {
            $targetIconName = $b['target'] === 'all' ? 'package.webp' : ($targets[$b['target']]['icon'] ?? 'document.webp');

            $dtos[] = new BackupViewDto(
                filename: $b['filename'],
                sizeMb: \round($b['size'] / 1024 / 1024, 2),
                dateFormatted: \date('d.m.Y — H:i:s', $b['date']),
                targetName: $b['target'],
                targetLabel: $b['target'] === 'all' ? 'Voll-Backup' : 'Tabelle: ' . $b['target'],
                targetIconUrl: $this->assetHelper->url('assets/img/icons/' . $targetIconName),
                tables: $b['tables'] ?? [],
            );
        }

        $targetOptions = [];
        foreach ($targets as $key => $data) {
            $targetOptions[] = ['value' => $key, 'label' => $data['emoji'] . ' ' . $data['label']];
        }

        $ftpEnabled = $this->config->get('backup_settings')['ftp']['enabled'] ?? false;

        return new BackupsResultDto($dtos, $targetOptions, $ftpEnabled);
    }
}
