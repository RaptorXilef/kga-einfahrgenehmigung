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
        $path = $this->getFilePath($cfg['file']);
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
     * Erhöht den Zähler oder aktualisiert den Zeitstempel.
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

            return;
        }

        // FIX: Sicheres Inkrementieren mit exklusivem File-Lock (LOCK_EX)
        $path = $this->getFilePath($cfg['file']);
        $fp   = @\fopen($path, 'c+');
        if ($fp && \flock($fp, \LOCK_EX)) {
            $stat = \fstat($fp);
            $size = $stat['size'];
            $raw  = $size > 0 ? \fread($fp, $size) : '';
            $data = JsonHelper::decode((string) $raw);

            if (! isset($data[$ip])) {
                $data[$ip] = ['attempts' => 1, 'last_attempt' => $nowStr];
            } else {
                $data[$ip]['attempts']     = (int) $data[$ip]['attempts'] + 1;
                $data[$ip]['last_attempt'] = $nowStr;
            }

            \ftruncate($fp, 0);
            \fseek($fp, 0);
            $jsonStr = \json_encode($data, \JSON_PRETTY_PRINT);
            if (\fwrite($fp, $jsonStr) === false) {
                throw new \RuntimeException('Kritischer Schreibfehler im RateLimiter.');
            }
            \fflush($fp);
            \flock($fp, \LOCK_UN);
            \fclose($fp);
        }
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

        // FIX: Sicheres Löschen mit exklusivem File-Lock (LOCK_EX)
        $path = $this->getFilePath($cfg['file']);
        $fp   = @\fopen($path, 'c+');
        if ($fp && \flock($fp, \LOCK_EX)) {
            $stat = \fstat($fp);
            $size = $stat['size'];
            $raw  = $size > 0 ? \fread($fp, $size) : '';
            $data = JsonHelper::decode((string) $raw);

            if (isset($data[$ip])) {
                unset($data[$ip]);
                \ftruncate($fp, 0);
                \fseek($fp, 0);
                $jsonStr = \json_encode($data, \JSON_PRETTY_PRINT);
                if (\fwrite($fp, $jsonStr) === false) {
                    throw new \RuntimeException('Kritischer Schreibfehler im RateLimiter.');
                }
                \fflush($fp);
            }
            \flock($fp, \LOCK_UN);
            \fclose($fp);
        }
    }

    // --- Private Utility ---

    /**
     * Hilfsmethode zur Auflösung des absoluten JSON-Speicherpfades.
     *
     * @param string $fileName Der Name der Zieldatei.
     *
     * @return string Der komplette Dateipfad.
     */
    private function getFilePath(string $fileName): string
    {
        return \rtrim((string) $this->config->get('root_path'), '/\\') . '/' .
            \ltrim((string) $this->config->get('storage_path_prefix'), '/\\') . $fileName;
    }
}
