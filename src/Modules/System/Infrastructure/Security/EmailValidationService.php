<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Security;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\System\Application\Contracts\EmailValidationServiceInterface;
use Exception;
use InvalidArgumentException;
use Override;

/**
 * Physische Implementierung der E-Mail-Validierung.
 * Kommuniziert via DNS (MX-Records) und HTTP (GitHub).
 * Darf native I/O Funktionen nutzen, da sie sich nun im Infrastructure-Layer befindet.
 */
final readonly class EmailValidationService implements EmailValidationServiceInterface
{
    public function __construct(
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function validate(string $email): void
    {
        $email = \trim($email);
        if ($email === '') {
            return;
        }

        if (!\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Die eingegebene E-Mail-Adresse ist ungültig.');
        }

        if (!\preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email)) {
            throw new InvalidArgumentException('Die E-Mail-Adresse enthält ungültige Sonderzeichen.');
        }

        $domain = \substr(\strrchr($email, '@'), 1);
        if ($domain === false) {
            throw new InvalidArgumentException('E-Mail-Domain konnte nicht extrahiert werden.');
        }

        $domainLower = \strtolower($domain);

        // 1. Blacklist Check (Trash-Mails + Custom Blacklist)
        $disposableDomains = $this->getDisposableDomains();
        if (\in_array($domainLower, $disposableDomains, true)) {
            throw new InvalidArgumentException('Diese E-Mail-Adresse wird vom System aus Sicherheitsgründen nicht akzeptiert (Spam-Schutz).');
        }

        // 2. DNS / MX Check (Physische Existenz)
        if (!\checkdnsrr($domainLower, 'MX')) {
            throw new InvalidArgumentException('Die E-Mail-Domain existiert nicht oder besitzt keinen gültigen Posteingangsserver.');
        }
    }

    #[Override]
    public function syncDisposableDomains(): void
    {
        $path = $this->config->getStoragePath('disposable_email.json');
        $now = $this->clock->now()->getTimestamp();

        // Nur updaten, wenn die Datei älter als 7 Tage ist (604800 Sekunden)
        if (\file_exists($path) && ($now - \filemtime($path)) < 604800) {
            return;
        }

        $url = 'https://raw.githubusercontent.com/eramitgupta/disposable-email/master/disposable_email.json';

        $ctx = \stream_context_create(['http' => ['timeout' => 5]]);
        $json = @\file_get_contents($url, false, $ctx);

        if ($json === false || !\json_validate($json)) {
            return;
        }

        @\file_put_contents($path, $json, \LOCK_EX);
    }

    private function getDisposableDomains(): array
    {
        $path = $this->config->getStoragePath('disposable_email.json');
        $customPath = $this->config->getStoragePath('settings/custom_email_blacklist.json');

        $domains = [];

        // 1. Die öffentliche Liste laden
        if (\file_exists($path)) {
            try {
                $domains = $this->jsonHelper->read($path);
            } catch (Exception) {
                // Fallback bei defekter JSON
            }
        }

        if ($domains === []) {
            $domains = ['mailinator.com', '10minutemail.com', 'tempmail.com', 'trashmail.com', 'yopmail.com'];
        }

        // 2. Die manuelle, vereinsspezifische Blacklist laden und anfügen
        if (\file_exists($customPath)) {
            try {
                $customDomains = $this->jsonHelper->read($customPath);
                if (\is_array($customDomains)) {
                    $domains = \array_merge($domains, $customDomains);
                }
            } catch (Exception) {
                // Bei Fehlern einfach ignorieren
            }
        }

        return \array_map(strtolower(...), $domains);
    }
}
