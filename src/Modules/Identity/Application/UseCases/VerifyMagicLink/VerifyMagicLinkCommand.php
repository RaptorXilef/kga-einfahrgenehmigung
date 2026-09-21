<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\VerifyMagicLink;

use App\SharedKernel\Application\Command\CommandInterface;

final readonly class VerifyMagicLinkCommand implements CommandInterface
{
    public function __construct(
        public string $input, // Kann das lange Token oder der kurze OTP Code sein
        public string $ipAddress,
    ) {
    }
}
