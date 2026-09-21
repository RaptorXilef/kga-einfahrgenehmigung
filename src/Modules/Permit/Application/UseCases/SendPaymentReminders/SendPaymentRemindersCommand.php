<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SendPaymentReminders;

use App\SharedKernel\Application\Command\CommandInterface;

final readonly class SendPaymentRemindersCommand implements CommandInterface
{
    public function __construct(
        public ?string $code = null,
        public bool $forceManual = false,
    ) {
    }
}
