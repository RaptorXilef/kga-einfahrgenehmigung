<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ConfirmPermitEmail;

use App\Modules\Permit\Domain\Permit;

final readonly class ConfirmPermitEmailResult
{
    public function __construct(
        public bool $isSuccess,
        public ?Permit $finalisedPermit = null,
        public ?string $checkoutToken = null,
        public ?array $verifiedData = null,
    ) {
    }
}
