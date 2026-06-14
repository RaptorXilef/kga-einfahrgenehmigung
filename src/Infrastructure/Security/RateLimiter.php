<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Infrastructure\Storage\JsonHelper;

/**
 * Implementierung des Rate-Limiters zum Schutz vor Brute-Force Logins.
 *
 * Speichert Fehlversuche je IP-Adresse (in MySQL oder JSON) und sperrt den
 * Zugang temporär nach Überschreiten der definierten Limits (Lockout-Time).
 *
 * Path: src/Infrastructure/Security/RateLimiter.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class RateLimiter implements RateLimiterInterface
{
    use \App\Infrastructure\Storage\JsonTransactionTrait;

    private const int MAX_ATTEMPTS    = 5;
    private const int LOCKOUT_MINUTES = 15;

    public function __construct(
        private ?\PDO $pdo,
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
        $now = new \DateTimeImmutable();

        if (($cfg['type'] ?? 'json') === 'mysql' && $this->pdo instanceof \PDO) {
            $stmt = $this->pdo->prepare("SELECT attempts, last_attempt FROM `{$cfg['table']}` WHERE ip_address = ?");
            $stmt->execute([$ip]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                $lastAttempt = new \DateTimeImmutable($row['last_attempt']);
                $diffMinutes = ($now->getTimestamp() - $lastAttempt->getTimestamp()) / 60;

                if ($diffMinutes > self::LOCKOUT_MINUTES) {
                    $this->clearAttempts($ip);

                    return false;
                }

                return (int) $row['attempts'] >= self::MAX_ATTEMPTS;
            }

            return false;
        }

        // JSON Fallback
        $path = $this->config->getStoragePath($cfg['file']);
        $data = JsonHelper::read($path);

        if (isset($data[$ip])) {
            $lastAttempt = new \DateTimeImmutable($data[$ip]['last_attempt']);
            $diffMinutes = ($now->getTimestamp() - $lastAttempt->getTimestamp()) / 60;

            if ($diffMinutes > self::LOCKOUT_MINUTES) {
                $this->clearAttempts($ip);

                return false;
            }

            return (int) $data[$ip]['attempts'] >= self::MAX_ATTEMPTS;
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
        $nowStr = APP_REQUEST_TIME_STR;

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
        $this->executeJsonTransaction($path, function (array &$data) use ($ip, $nowStr) {
            // Garbage Collection (Speicher-Leck / JSON-Bloat verhindern!)
            // Löscht alle IPs, deren letzter Versuch länger als die Lockout-Zeit her ist.
            $threshold = \time() - (self::LOCKOUT_MINUTES * 60);
            foreach ($data as $storedIp => $info) {
                if (isset($info['last_attempt']) && \strtotime($info['last_attempt']) < $threshold) {
                    unset($data[$storedIp]);
                }
            }
            $data[$ip]['attempts']     = ($data[$ip]['attempts'] ?? 0) + 1;
            $data[$ip]['last_attempt'] = $nowStr;

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
        $this->executeJsonTransaction($path, function (array &$data) use ($ip) {
            if (isset($data[$ip])) {
                unset($data[$ip]);

                return true;
            }

            return false;
        });
    }
}
