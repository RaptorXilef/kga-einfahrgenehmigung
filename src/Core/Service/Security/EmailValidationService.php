<?php

declare(strict_types=1);

namespace App\Core\Service\Security;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\JsonHelperInterface;
use Exception;
use InvalidArgumentException;

/**
 * Service für tiefe E-Mail-Validierung.
 * Blockiert Wegwerf-E-Mails (via dynamischer JSON) und prüft die physische Erreichbarkeit der Domain via DNS/MX.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class EmailValidationService
{
    public function __construct(
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    /**
     * @throws InvalidArgumentException Wenn die E-Mail ungültig, eine Trash-Mail oder unerreichbar ist.
     */
    public function validate(string $email): void
    {
        $email = \trim($email);
        if ($email === '') {
            return;
        }

        if (!\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Die eingegebene E-Mail-Adresse ist ungültig.');
        }

        // Strikte Regex für den lokalen Teil (vor dem @) - Verhindert unübliche Sonderzeichen wie #, ! etc.
        // Erlaubt weiterhin saubere Adressen wie test.zwei@ oder test+newsletter@
        if (!\preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email)) {
            throw new InvalidArgumentException('Die E-Mail-Adresse enthält ungültige Sonderzeichen.');
        }

        $domain = \substr(\strrchr($email, '@'), 1);
        if ($domain === false) {
            throw new InvalidArgumentException('E-Mail-Domain konnte nicht extrahiert werden.');
        }

        $domainLower = \strtolower($domain);

        // 1. Blacklist Check (Trash-Mails) aus tagesaktueller JSON
        $disposableDomains = $this->getDisposableDomains();
        if (\in_array($domainLower, $disposableDomains, true)) {
            throw new InvalidArgumentException('Wegwerf-E-Mail-Adressen sind aus Sicherheitsgründen nicht gestattet.');
        }

        // 2. DNS / MX Check (Physische Existenz)
        // WICHTIG: Wir prüfen absichtlich NUR auf MX. Eine Domain ohne MX-Record kann keine sauberen Mails empfangen!
        /* && !\checkdnsrr($domainLower, 'A') */
        if (!\checkdnsrr($domainLower, 'MX')) {
            throw new InvalidArgumentException('Die E-Mail-Domain existiert nicht oder besitzt keinen gültigen Posteingangsserver.');
        }
    }

    /**
     * Aktualisiert die Anti-Spam-Liste automatisch von GitHub.
     * Wird über den isolierten Cronjob angetriggert.
     */
    public function syncDisposableDomains(): void
    {
        $path = $this->config->getStoragePath('disposable_email.json');

        // Nur updaten, wenn die Datei älter als 7 Tage ist (604800 Sekunden)
        if (\file_exists($path) && (\time() - \filemtime($path)) < 604800) {
            return;
        }

        $url = 'https://raw.githubusercontent.com/eramitgupta/disposable-email/master/disposable_email.json';

        $ctx = \stream_context_create(['http' => ['timeout' => 5]]);
        $json = @\file_get_contents($url, false, $ctx);

        if ($json !== false && \json_validate($json)) {
            @\file_put_contents($path, $json, \LOCK_EX);
        }
    }

    /**
     * Lädt die dynamische Liste oder fällt auf einen harten Kern zurück.
     *
     * @return array<int, string>
     */
    private function getDisposableDomains(): array
    {
        $path = $this->config->getStoragePath('disposable_email.json');

        if (\file_exists($path)) {
            try {
                return $this->jsonHelper->read($path);
            } catch (Exception) {
                // Fallback bei defekter JSON
            }
        }

        // Minimaler Fallback
        return ['mailinator.com', '10minutemail.com', 'tempmail.com', 'trashmail.com', 'yopmail.com'];
    }
}
