<?php

declare(strict_types=1);

namespace App\Contracts\Security;

use InvalidArgumentException;

/**
 * Globaler Port für die tiefe E-Mail-Validierung (MX-Check & Disposable-Domain-Schutz).
 */
interface EmailValidationInterface
{
    /**
     * @throws InvalidArgumentException Wenn die E-Mail ungültig, eine Trash-Mail oder unerreichbar ist.
     */
    public function validate(string $email): void;

    /**
     * Aktualisiert die Anti-Spam-Liste automatisch.
     */
    public function syncDisposableDomains(): void;
}
