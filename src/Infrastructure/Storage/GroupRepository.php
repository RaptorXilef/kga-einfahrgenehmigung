<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\GroupRepositoryInterface;

// TODO DocBlock
final readonly class GroupRepository implements GroupRepositoryInterface
{
    use ImageUploadTrait;

    public function __construct(private ?\PDO $pdo, private ConfigInterface $config)
    {
    }

    /**
     * Lädt alle Berechtigungsgruppen und Rollen aus Live aus der 'groups.json' bzw. dem konfigurierten Speicher.
     *
     * @return array<string, array<string, mixed>>
     */
    public function loadAll(): array
    {
        $cfg = $this->config->get('storage_config')['groups'];
        if (($cfg['type'] ?? 'json') === 'mysql' && $this->pdo instanceof \PDO) {
            $stmt   = $this->pdo->query("SELECT * FROM {$cfg['table']}");
            $groups = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $perms = \is_string(
                    $row['permissions'],
                ) ? \json_decode(
                    $row['permissions'],
                    true,
                ) : $row['permissions'];
                $groups[$row['id']] = ['name' => $row['name'], 'permissions' => $perms ?? []];
            }

            return $groups;
        }

        $path = \rtrim(
            (string) $this->config->get('root_path'),
            '/\\',
        ) . '/' . \ltrim(
            (string) $this->config->get('storage_path_prefix'),
            '/\\',
        ) . $cfg['file'];

        return \file_exists($path)
            && ! \is_dir($path) ? (\json_decode((string) \file_get_contents($path), true) ?? []) : [];
    }

    /**
     * Persistiert das Gruppen- und Rollen-Array im Dateisystem.
     *
     * @param array<string, array<string, mixed>> $groups
     */
    public function saveAll(array $groups, bool $forceSql = false): void
    {
        $cfg    = $this->config->get('storage_config')['groups'];
        $useSql = $forceSql || (($cfg['type'] ?? 'json') === 'mysql');

        if ($useSql && $this->pdo instanceof \PDO) {
            $this->pdo->beginTransaction();

            try {
                $this->pdo->exec("DELETE FROM {$cfg['table']}");
                $stmt = $this->pdo->prepare("INSERT INTO {$cfg['table']} (id, name, permissions) VALUES (?, ?, ?)");
                foreach ($groups as $id => $g) {
                    // WICHTIG: Arrays für MySQL als String kodieren!
                    $stmt->execute([$id, $g['name'], \json_encode($g['permissions'] ?? [], \JSON_UNESCAPED_UNICODE)]);
                }
                $this->pdo->commit();
            } catch (\Exception $e) {
                $this->pdo->rollBack();

                throw $e;
            }
            if ($forceSql) {
                return;
            }
        }

        if (! $forceSql) {
            $path = \rtrim((string) $this->config->get('root_path'), '/\\') . '/' .
                \ltrim((string) $this->config->get('storage_path_prefix'), '/\\') . $cfg['file'];
            \file_put_contents($path, \json_encode($groups, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
        }
    }

    public function uploadImage(string $groupId, array $file): bool
    {
        return $this->doUploadImage('group_images', $groupId, $file, (string) $this->config->get('root_path'));
    }

    public function getImageUrl(string $groupId): string
    {
        return $this->doGetImageUrl(
            'group_images',
            $groupId,
            'icon-group-default.webp',
            (string) $this->config->get('root_path'),
            $this->config->getBaseUrl(),
        );
    }
}
