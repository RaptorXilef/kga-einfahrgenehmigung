<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SubmitPermitRequest;

final readonly class SubmitPermitResult
{
    public function __construct(
        public string $action, // 'redirect_verify', 'redirect_checkout'
        public ?string $token = null,
    ) {
    }
}
