<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageMails;

use App\SharedKernel\Application\Command\CommandInterface;

/**
 * Command zum erneuten Versenden einer protokollierten E-Mail anhand ihres Zeitstempels.
 */
final readonly class ResendMailCommand implements CommandInterface
{
    public function __construct(
        public string $timestamp,
    ) {
    }
}
