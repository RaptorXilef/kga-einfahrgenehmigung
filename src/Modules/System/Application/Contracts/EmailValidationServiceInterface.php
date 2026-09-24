<?php

declare(strict_types=1);

namespace App\Modules\System\Application\Contracts;

use InvalidArgumentException;

/**
 * Entkoppelt die komplexe I/O (DNS/MX Checks, HTTP Fetch für Spam-Listen)
 * von der Application-Schicht.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface EmailValidationServiceInterface
{
    /**
     * @throws InvalidArgumentException Wenn die E-Mail ungültig, eine Trash-Mail oder unerreichbar ist.
     */
    public function validate(string $email): void;

    /**
     * Aktualisiert die Anti-Spam-Liste automatisch von GitHub.
     */
    public function syncDisposableDomains(): void;
}
