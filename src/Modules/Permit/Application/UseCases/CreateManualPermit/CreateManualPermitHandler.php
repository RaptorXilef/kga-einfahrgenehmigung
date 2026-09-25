<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\CreateManualPermit;

use App\Contracts\Event\EventDispatcherInterface;
use App\Modules\Permit\Domain\Events\PermitCreatedEvent;
use App\Modules\Permit\Domain\Owner;
use App\Modules\Permit\Domain\PermitFactory;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Domain\Vehicle;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use App\SharedKernel\Application\Command\CommandInterface;
use DateTimeImmutable;
use Override;

/**
 * @implements CommandHandlerInterface<CreateManualPermitCommand>
 */
final readonly class CreateManualPermitHandler implements CommandHandlerInterface
{
    public function __construct(
        private PermitRepositoryInterface $repository,
        private PermitFactory $permitFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param CreateManualPermitCommand $command
     */
    #[Override]
    public function handle(CommandInterface $command): void
    {
        $startDate = new DateTimeImmutable($command->datumVon);
        $customEndDate = $command->datumBis !== '' ? new DateTimeImmutable($command->datumBis) : null;

        $permit = $this->permitFactory->createNew(
            templateKey: clone $command->templateKey,
            owner: new Owner(\strip_tags($command->name), $command->email, clone $command->parzelle),
            vehicle: new Vehicle($command->typ, clone $command->kennzeichen, $command->firma ? \strip_tags($command->firma) : null),
            startDate: $startDate,
            customEndDate: $customEndDate,
            price: clone $command->manualPrice,
            purpose: $command->zweck,
            status: $command->status,
            internerKommentar: $command->internerKommentar,
            agreements: $command->agreements,
        );

        $this->repository->save($permit);

        if (!$command->sendEmail) {
            return;
        }

        $codeParts = \explode('-', $permit->code->value);
        $shortCode = \end($codeParts);
        $this->eventDispatcher->dispatch(new PermitCreatedEvent($permit, $shortCode, $permit->getCreatedAt()));
    }
}
