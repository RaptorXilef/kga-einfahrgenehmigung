<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ConfirmPermitEmail;

final readonly class ConfirmPermitEmailResult
{
    public function __construct(
        public bool $isSuccess,
        // VSA CQRS FIX: Hält nicht mehr die Entity, sondern nur noch den Identifier String
        public ?string $finalisedPermitCode = null,
        public ?string $checkoutToken = null,
        public ?array $verifiedData = null,
    ) {
    }
}
