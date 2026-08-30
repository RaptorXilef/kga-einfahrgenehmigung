<?php

declare(strict_types=1);

namespace App\Core\Service\Security;

use InvalidArgumentException;

/**
 * Service für tiefe E-Mail-Validierung.
 * Blockiert Wegwerf-E-Mails und prüft die physische Erreichbarkeit der Domain via DNS/MX.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class EmailValidationService
{
    /**
     * @var string[] Liste bekannter Trash-Mail Provider.
     */
    private const array DISPOSABLE_DOMAINS = [
        'mailinator.com', '10minutemail.com', 'tempmail.com', 'temp-mail.org',
        'guerrillamail.com', 'trashmail.com', 'yopmail.com', 'throwawaymail.com',
        'fakemail.net', 'wegwerfmail.de', 'trash-mail.com', 'dispostable.com',
    ];

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

        // 1. Blacklist Check (Trash-Mails)
        if (\in_array($domainLower, self::DISPOSABLE_DOMAINS, true)) {
            throw new InvalidArgumentException('Wegwerf-E-Mail-Adressen sind aus Sicherheitsgründen nicht gestattet.');
        }

        // 2. DNS / MX Check (Physische Existenz)
        if (!\checkdnsrr($domainLower, 'MX') && !\checkdnsrr($domainLower, 'A')) {
            throw new InvalidArgumentException('Die E-Mail-Domain existiert nicht oder kann keine Nachrichten empfangen.');
        }
    }
}
