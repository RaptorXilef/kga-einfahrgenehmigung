<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\DeleteVoucher;

use App\Modules\Voucher\Domain\VoucherRepositoryInterface;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use Override;

/**
 * @implements CommandHandlerInterface<DeleteVoucherCommand>
 */
final readonly class DeleteVoucherHandler implements CommandHandlerInterface
{
    public function __construct(
        private VoucherRepositoryInterface $repository,
    ) {
    }

    /**
     * @param DeleteVoucherCommand $command
     */
    #[Override]
    public function handle(mixed $command): void
    {
        // Wir delegieren das Löschen direkt an die Infrastruktur.
        // Ist idempotent: Wenn er nicht existiert, passiert nichts.
        $this->repository->delete($command->code);
    }
}
