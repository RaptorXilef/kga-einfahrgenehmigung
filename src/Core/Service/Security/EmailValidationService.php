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
        if (!\checkdnsrr($domainLower, 'MX') && !\checkdnsrr($domainLower, 'A')) {
            throw new InvalidArgumentException('Die E-Mail-Domain existiert nicht oder kann keine Nachrichten empfangen.');
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

        // Minimaler Fallback, falls die Datei noch nicht synchronisiert wurde
        return ['mailinator.com', '10minutemail.com', 'tempmail.com', 'trashmail.com', 'yopmail.com'];
    }
}
