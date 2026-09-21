<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\TogglePermitSuspension;

use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use DomainException;

/**
 * @implements CommandHandlerInterface<TogglePermitSuspensionCommand>
 */
final readonly class TogglePermitSuspensionHandler implements CommandHandlerInterface
{
    public function __construct(
        private PermitRepositoryInterface $repository,
    ) {
    }

    /**
     * @param TogglePermitSuspensionCommand $command
     */
    public function handle(mixed $command): void
    {
        $permit = $this->repository->findByCode($command->code);

        if ($permit === null) {
            throw new DomainException('Genehmigung nicht gefunden.');
        }

        if ($command->isSuspended) {
            $permit->suspend($command->reason);
        } else {
            $permit->unsuspend();
        }

        $this->repository->save($permit);
    }
}
