<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SubmitPermitRequest;

use App\Modules\Permit\Application\DTO\PermitFormData;
use App\SharedKernel\Application\Command\CommandInterface;

final readonly class SubmitPermitRequestCommand implements CommandInterface
{
    public function __construct(
        public PermitFormData $formData,
        public ?string $editToken = null,
        public ?string $sessionEmail = null,
    ) {
    }
}
