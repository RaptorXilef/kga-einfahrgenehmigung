<?php

declare(strict_types=1);

namespace App\Modules\System\Application\Contracts;

use App\Contracts\Security\EmailValidationInterface;

/**
 * Entkoppelt die komplexe I/O (DNS/MX Checks, HTTP Fetch für Spam-Listen)
 * von der Application-Schicht.
 */
interface EmailValidationServiceInterface extends EmailValidationInterface
{
}
