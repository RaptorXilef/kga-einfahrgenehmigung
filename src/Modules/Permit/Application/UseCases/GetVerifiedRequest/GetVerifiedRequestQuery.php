<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetVerifiedRequest;

final readonly class GetVerifiedRequestQuery
{
    public function __construct(public string $token)
    {
    }
}
