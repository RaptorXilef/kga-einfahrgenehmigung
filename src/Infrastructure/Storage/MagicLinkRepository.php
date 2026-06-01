<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\MagicLinkRepositoryInterface;

// TODO DocBlocks
final readonly class MagicLinkRepository implements MagicLinkRepositoryInterface
{
    public function __construct(
        private ?\PDO $pdo,
        private ConfigInterface $config,
    ) {
    }

    /**
     * Lädt den aktuellen Bestand an ungelösten Token-Referenzen aus dem konfigurierten Backend.
     *
     * @return array<string, array{email: string, code: string, expires: int}> Liste aktiver Tokens indiziert nach
     *                                                                         Krypto-Hash.
     */
    public function loadAll(): array
    {
        $cfg   = $this->config->get('storage_config')['magic_links'];
        $links = [];

        if ($cfg['type'] === 'mysql') {
            if ($this->pdo instanceof \PDO) {
                $stmt = $this->pdo->query("SELECT * FROM {$cfg['table']}");
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                    $links[$r['token']] = [
                        'email'   => $r['email'],
                        'code'    => $r['code'],
                        'expires' => $r['expires'],
                    ];
                }
            }
        } else {
            $path = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
            if (\file_exists($path)) {
                $links = \json_decode((string) \file_get_contents($path), true) ?? [];
            }
        }

        // On-the-fly Konvertierung für alte Integer Timestamps
        foreach ($links as &$l) {
            if (isset($l['expires']) && \is_numeric($l['expires'])) {
                $l['expires'] = \date('Y-m-d H:i:s', (int) $l['expires']);
            }
        }

        return $links;
    }

    /**
     * Persistiert die übergebene Token-Liste im aktiven Speicher-Subsystem (MySQL-Truncate/Insert oder JSON).
     *
     * @param array<string, array{email: string, code: string, expires: int}> $links Das zu schreibende Token-Array.
     */
    public function saveAll(array $links, bool $forceSql = false): void
    {
        $cfg    = $this->config->get('storage_config')['magic_links'];
        $useSql = $forceSql || (($cfg['type'] ?? 'json') === 'mysql');

        if ($useSql && $this->pdo instanceof \PDO) {
            $this->pdo->exec("DELETE FROM {$cfg['table']}");
            $stmt = $this->pdo->prepare("INSERT INTO {$cfg['table']} (token, email, code, expires) VALUES (?, ?, ?, ?)");

            foreach ($links as $token => $d) {
                $exp = $d['expires'] ?? \date('Y-m-d H:i:s');
                if (\is_numeric($exp)) {
                    $exp = \date('Y-m-d H:i:s', (int) $exp);
                }
                $stmt->execute([$token, $d['email'], $d['code'], $exp]);
            }
            if ($forceSql) {
                return;
            }
        }

        if (! $forceSql) {
            $path = \rtrim((string) $this->config->get('root_path'), '/\\') . '/' . \ltrim((string) $this->config->get('storage_path_prefix'), '/\\') . $cfg['file'];
            \file_put_contents($path, \json_encode($links, \JSON_PRETTY_PRINT));
        }
    }
}
