<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ConfirmPermitEmail;

final readonly class VerificationRenderRequest
{
    public function __construct(
        public bool $isError,
    ) {
    }

    public static function fromArray(array $get): self
    {
        return new self(isset($get['error']));
    }
}
