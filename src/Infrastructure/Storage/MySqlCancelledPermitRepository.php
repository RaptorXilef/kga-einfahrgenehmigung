<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\CancelledPermitRepositoryInterface;
use App\Core\Entity\Permit;

final readonly class MySqlCancelledPermitRepository implements CancelledPermitRepositoryInterface
{
    use StorageMapperTrait;

    public function __construct(private \PDO $pdo, private ConfigInterface $config)
    {
    }

    public function saveCancelled(Permit $permit): void
    {
        $table = $this->config->get('storage_config')['cancelled_permits']['table'];
        $sql   = "REPLACE INTO `{$table}` (code, template_key, name, email, kennzeichen, parzelle, typ, firma, zweck, preis, von, bis, status, erstellt, interner_kommentar, is_anonymized, agreements, bezahlt_am) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt  = $this->pdo->prepare($sql);
        $item  = $this->flattenEntity($permit);

        $stmt->execute([
            $item['code'], $item['template_key'], $item['name'], $item['email'], $item['kennzeichen'],
            $item['parzelle'], $item['typ'], $item['firma'], $item['zweck'], $item['preis'],
            $item['von'], $item['bis'], $item['status'], $item['erstellt'], $item['interner_kommentar'],
            1, $item['agreements'] ?? '{}', $item['bezahlt_am'],
        ]);
    }

    public function isCodeCancelled(string $code): bool
    {
        $table = $this->config->get('storage_config')['cancelled_permits']['table'];
        $stmt  = $this->pdo->prepare("SELECT code FROM `{$table}` WHERE code = ?");
        $stmt->execute([$code]);

        return (bool) $stmt->fetch();
    }

    public function loadAll(): array
    {
        $table = $this->config->get('storage_config')['cancelled_permits']['table'];
        $stmt  = $this->pdo->query("SELECT * FROM `{$table}` ORDER BY erstellt DESC");

        return \array_map($this->mapToEntity(...), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
}
