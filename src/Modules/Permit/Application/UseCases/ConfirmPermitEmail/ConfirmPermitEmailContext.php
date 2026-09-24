<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ConfirmPermitEmail;

final class ConfirmPermitEmailContext
{
    public bool $isSuccess = false;

    // VSA CQRS FIX: Hält nicht mehr die Entity, sondern nur noch den Identifier String
    public ?string $finalisedPermitCode = null;

    public ?string $checkoutToken = null;

    public ?array $verifiedData = null;
}
