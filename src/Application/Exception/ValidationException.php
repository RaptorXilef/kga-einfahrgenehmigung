<?php

declare(strict_types=1);

namespace App\Application\Exception;

use DomainException;

/**
 * Wird geworfen, wenn die Eingabedaten eines Request-DTOs ungültig sind.
 */
final class ValidationException extends DomainException
{
    /**
     * Erzeugt eine neue ValidationException mit einer benutzerfreundlichen Fehlermeldung.
     */
    public static function withMessage(string $message): self
    {
        return new self($message);
    }
}
