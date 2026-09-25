<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageMails;

/**
 * Ergebnis-DTO nach dem erneuten Einreihen einer E-Mail für Audit-Log und Flash-Message.
 */
final readonly class ResendMailResult
{
    public function __construct(
        public string $recipient,
        public string $subject,
    ) {
    }
}
