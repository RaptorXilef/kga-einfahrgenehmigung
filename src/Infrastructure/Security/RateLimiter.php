<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Utils\ClockInterface;
use App\Core\Entity\LoginAttempt;
use App\Infrastructure\Storage\JsonHelper;
use App\Infrastructure\Storage\JsonTransactionTrait;

/**
 * Implementierung des Rate-Limiters zum Schutz vor Brute-Force Logins.
 *
 * Speichert Fehlversuche je IP-Adresse (in MySQL oder JSON) und sperrt den
 * Zugang temporär nach Überschreiten der definierten Limits (Lockout-Time).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class RateLimiter implements RateLimiterInterface
{
    use JsonTransactionTrait;

    private const int MAX_ATTEMPTS    = 5;
    private const int LOCKOUT_MINUTES = 15;

    public function __construct(
        private ?\PDO $pdo,
        private ClockInterface $clock,
        private ConfigInterface $config,
    ) {
    }

    // --- Public API --

    /**
     * Prüft, ob die IP-Adresse blockiert ist, da die maximalen Fehlversuche überschritten wurden.
     * Gibt die IP automatisch nach Ablauf der Lockout-Zeit wieder frei.
     *
     * @param string $ip Die zu prüfende IP-Adresse.
     *
     * @return bool True, wenn die IP gesperrt ist.
     */
    public function isBlocked(string $ip): bool
    {
        $cfg = $this->config->get('storage_config')['login_attempts'];
        $now = $this->clock->now();

        if (($cfg['type'] ?? 'json') === 'mysql' && $this->pdo instanceof \PDO) {
            $stmt = $this->pdo->prepare("SELECT attempts, last_attempt FROM `{$cfg['table']}` WHERE ip_address = ?");
            $stmt->execute([$ip]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                $attempt     = new LoginAttempt($ip, (int) $row['attempts'], new \DateTimeImmutable($row['last_attempt']));
                $diffMinutes = ($now->getTimestamp() - $attempt->lastAttempt->getTimestamp()) / 60;

                if ($diffMinutes > self::LOCKOUT_MINUTES) {
                    $this->clearAttempts($ip);

                    return false;
                }

                return $attempt->attempts >= self::MAX_ATTEMPTS;
            }

            return false;
        }

        // JSON Fallback
        $path = $this->config->getStoragePath($cfg['file']);
        if (! \file_exists($path)) {
            return false;
        }

        $data = JsonHelper::read($path);

        if (isset($data[$ip])) {
            $attempt     = new LoginAttempt($ip, (int) $data[$ip]['attempts'], new \DateTimeImmutable($data[$ip]['last_attempt']));
            $diffMinutes = ($now->getTimestamp() - $attempt->lastAttempt->getTimestamp()) / 60;

            if ($diffMinutes > self::LOCKOUT_MINUTES) {
                $this->clearAttempts($ip);

                return false;
            }

            return $attempt->attempts >= self::MAX_ATTEMPTS;
        }

        return false;
    }

    /**
     * Registriert einen neuen Fehlversuch für die gegebene IP-Adresse.
     * Erhöht den Zähler oder aktualisiert den Zeitstempel und bereinigt alte Einträge.
     *
     * @param string $ip Die betroffene IP-Adresse.
     */
    public function recordFailedAttempt(string $ip): void
    {
        $cfg    = $this->config->get('storage_config')['login_attempts'];
        $nowStr = $this->clock->nowAsString();

        if (($cfg['type'] ?? 'json') === 'mysql' && $this->pdo instanceof \PDO) {
            $sql = "INSERT INTO `{$cfg['table']}` (ip_address, attempts, last_attempt)
                    VALUES (?, 1, ?)
                    ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = VALUES(last_attempt)";
            $this->pdo->prepare($sql)->execute([$ip, $nowStr]);

            // Optional: Auch in MySQL eine Garbage Collection einbauen, um die Tabelle schlank zu halten
            $this->pdo->prepare("DELETE FROM `{$cfg['table']}` WHERE last_attempt < DATE_SUB(NOW(), INTERVAL ? MINUTE)")
                ->execute([self::LOCKOUT_MINUTES]);

            return;
        }

        // Sicheres Inkrementieren mit exklusivem File-Lock (LOCK_EX)
        $path = $this->config->getStoragePath($cfg['file']);
        $this->executeJsonTransaction($path, function (array &$data) use ($ip, $nowStr): bool {
            // Garbage Collection (Speicher-Leck / JSON-Bloat verhindern!)
            // Löscht alle IPs, deren letzter Versuch länger als die Lockout-Zeit her ist.
            $threshold = \time() - (self::LOCKOUT_MINUTES * 60);

            // Garbage Collection alter Einträge
            foreach ($data as $storedIp => $info) {
                if (isset($info['last_attempt']) && \strtotime($info['last_attempt']) < $threshold) {
                    unset($data[$storedIp]);
                }
            }

            $attempts = ($data[$ip]['attempts'] ?? 0) + 1;
            $entity   = new LoginAttempt($ip, $attempts, new \DateTimeImmutable($nowStr));

            $data[$ip] = [
                'attempts'     => $entity->attempts,
                'last_attempt' => $entity->lastAttempt->format('Y-m-d H:i:s'),
            ];

            return true;
        });
    }

    /**
     * Löscht alle registrierten Fehlversuche für eine IP-Adresse.
     * Wird nach einem erfolgreichen Login oder nach Ablauf der Sperrzeit aufgerufen.
     *
     * @param string $ip Die betroffene IP-Adresse.
     */
    public function clearAttempts(string $ip): void
    {
        $cfg = $this->config->get('storage_config')['login_attempts'];

        if (($cfg['type'] ?? 'json') === 'mysql' && $this->pdo instanceof \PDO) {
            $this->pdo->prepare("DELETE FROM `{$cfg['table']}` WHERE ip_address = ?")->execute([$ip]);

            return;
        }

        // Sicheres Löschen mit exklusivem File-Lock (LOCK_EX)
        $path = $this->config->getStoragePath($cfg['file']);
        $this->executeJsonTransaction($path, function (array &$data) use ($ip): bool {
            if (isset($data[$ip])) {
                unset($data[$ip]);

                return true;
            }

            return false;
        });
    }

    public function import(array $data, bool $forceSql = false): void
    {
        $cfg    = $this->config->get('storage_config')['login_attempts'];
        $useSql = $forceSql || (($cfg['type'] ?? 'json') === 'mysql');

        $objects = [];
        foreach ($data as $ip => $item) {
            $objects[$ip] = new LoginAttempt(
                (string) $ip,
                (int) ($item['attempts'] ?? 0),
                new \DateTimeImmutable($item['last_attempt'] ?? 'now'),
            );
        }

        if ($useSql && $this->pdo instanceof \PDO) {
            $this->pdo->beginTransaction();

            try {
                $stmt = $this->pdo->prepare("REPLACE INTO `{$cfg['table']}` (ip_address, attempts, last_attempt) VALUES (?, ?, ?)");
                foreach ($objects as $obj) {
                    $stmt->execute([$obj->ipAddress, $obj->attempts, $obj->lastAttempt->format('Y-m-d H:i:s')]);
                }
                $this->pdo->commit();
            } catch (\Exception $e) {
                $this->pdo->rollBack();

                throw $e;
            }
        } elseif (! $forceSql) {
            $path   = $this->config->getStoragePath($cfg['file']);
            $toSave = [];
            foreach ($objects as $obj) {
                $toSave[$obj->ipAddress] = [
                    'attempts'     => $obj->attempts,
                    'last_attempt' => $obj->lastAttempt->format('Y-m-d H:i:s'),
                ];
            }
            \file_put_contents($path, \json_encode($toSave, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE), \LOCK_EX);
        }
    }
}
