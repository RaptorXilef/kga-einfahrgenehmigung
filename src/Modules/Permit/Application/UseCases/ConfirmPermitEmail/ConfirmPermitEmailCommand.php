<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ConfirmPermitEmail;

final readonly class ConfirmPermitEmailCommand
{
    public function __construct(public string $tokenOrCode)
    {
    }
}
