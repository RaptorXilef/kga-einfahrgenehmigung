<?php

declare(strict_types=1);

namespace App\Application\Exception;

/**
 * Wird geworfen, wenn die Formulardaten (DTO) ungültig sind.
 *
 * Path: src/Application/Exception/ValidationException.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final class ValidationException extends \DomainException
{
    // TODO DOCBLOCK
    public static function withMessage(string $message): self
    {
        return new self($message);
    }
}
