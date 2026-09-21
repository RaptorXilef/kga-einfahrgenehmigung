<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\CreateManualPermit;

use App\Modules\Permit\Application\DTO\PermitFormData; // Neues DTO
use App\SharedKernel\Application\Command\CommandInterface;

final readonly class CreateManualPermitCommand implements CommandInterface
{
    public function __construct(
        public PermitFormData $formData,
        public bool $sendEmail,
    ) {
    }
}
