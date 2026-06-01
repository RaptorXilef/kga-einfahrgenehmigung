<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;

// TODO DocBlock
final readonly class VerificationRepository implements VerificationRepositoryInterface
{
    public function __construct(
        private ?\PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    public function loadPending(): array
    {
        $data   = $this->loadJson('pending_verification');
        $nowStr = \date('Y-m-d H:i:s');

        return \array_filter($data, fn (array $item): bool => isset($item['expires']) && $item['expires'] > $nowStr);
    }

    public function savePending(array $data, bool $forceSql = false): void
    {
        $this->saveJson('pending_verification', $data, $forceSql);
    }

    public function loadVerified(): array
    {
        return $this->loadJson('verified_pending');
    }

    public function saveVerified(array $data, bool $forceSql = false): void
    {
        $this->saveJson('verified_pending', $data, $forceSql);
    }

    // Lädt temporäre Antragssitzungen und filtert im 'pending'-Status abgelaufene TTLs automatisch heraus.
    private function loadJson(string $targetKey): array
    {
        $cfg  = $this->config->get('storage_config')[$targetKey];
        $data = [];

        if ($cfg['type'] === 'mysql') {
            if ($this->pdo instanceof \PDO) {
                $stmt = $this->pdo->query("SELECT * FROM {$cfg['table']}");
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                    $data[$r['token']]            = \json_decode((string) $r['data'], true);
                    $data[$r['token']]['expires'] = $r['expires'];
                }
            }
        } else {
            $path = $this->getFilePath($cfg['file']);
            if (\file_exists($path)) {
                $data = (array) \json_decode((string) \file_get_contents($path), true) ?? [];
            }
        }

        // On-the-fly Konvertierung alter Integer-Timestamps
        foreach ($data as &$item) {
            if (isset($item['expires']) && \is_numeric($item['expires'])) {
                $item['expires'] = \date('Y-m-d H:i:s', (int) $item['expires']);
            }
        }

        return $data;
    }

    // Speichert temporäre Antragssitzungen ab (Unterstützt flache JSONs oder relationale MySQL-Tabellen).
    private function saveJson(string $targetKey, array $data, bool $forceSql = false): void
    {
        $cfg    = $this->config->get('storage_config')[$targetKey];
        $useSql = $forceSql || ($cfg['type'] === 'mysql');

        if ($useSql && $this->pdo instanceof \PDO) {
            $this->pdo->exec("DELETE FROM {$cfg['table']}");
            $stmt = $this->pdo->prepare("INSERT INTO {$cfg['table']} (token, expires, data) VALUES (?, ?, ?)");
            foreach ($data as $token => $item) {
                $exp = $item['expires'] ?? \date('Y-m-d H:i:s');
                if (\is_numeric($exp)) {
                    $exp = \date('Y-m-d H:i:s', (int) $exp);
                }
                $stmt->execute([$token, $exp, \json_encode($item, \JSON_UNESCAPED_UNICODE)]);
            }
            if ($forceSql) {
                return;
            }
        }

        if (! $forceSql) {
            $path = $this->getFilePath($cfg['file']);
            \file_put_contents($path, \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
        }
    }

    private function getFilePath(string $fileName): string
    {
        return \rtrim((string) $this->config->get('root_path'), '/\\') . '/' .
               \ltrim((string) $this->config->get('storage_path_prefix'), '/\\') . $fileName;
    }
}
