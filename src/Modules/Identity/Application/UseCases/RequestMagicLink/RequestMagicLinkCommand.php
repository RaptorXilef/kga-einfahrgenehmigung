<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\RequestMagicLink;

use App\SharedKernel\Application\Command\CommandInterface;

final readonly class RequestMagicLinkCommand implements CommandInterface
{
    public function __construct(
        public string $email,
    ) {
    }
}
