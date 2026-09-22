<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\CancelPermit;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Utils\ClockInterface;
use App\Core\Event\PermitCancelledEvent;
use App\Core\Security\Sanitizer;
use App\Modules\Permit\Domain\CancelledPermitRepositoryInterface;
use App\Modules\Permit\Domain\Owner;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Domain\PermitStatus;
use App\Modules\Permit\Domain\Status;
use App\Modules\Permit\Domain\Vehicle;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use DomainException;

/**
 * @implements CommandHandlerInterface<CancelPermitCommand>
 */
final readonly class CancelPermitHandler implements CommandHandlerInterface
{
    public function __construct(
        private ConfigInterface $config,
        private PermitRepositoryInterface $repository,
        private CancelledPermitRepositoryInterface $cancelledRepository,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(mixed $command): void
    {
        if (!$this->config->get('allow_user_cancellation', true)) {
            throw new DomainException('Stornierungen sind derzeit deaktiviert.');
        }

        $permit = $this->repository->findByCode($command->code);

        if (!$permit instanceof Permit) {
            throw new DomainException('Genehmigung nicht gefunden.');
        }

        if (Sanitizer::normalizeEmail($permit->getOwnerEmail()) !== Sanitizer::normalizeEmail($command->sessionEmail)) {
            throw new DomainException('Keine Berechtigung für diese Genehmigung.');
        }

        if ($permit->isPaid()) {
            throw new DomainException('Bereits bezahlte Genehmigungen können nicht automatisch storniert werden.');
        }

        $now = $this->clock->now();

        if (!$permit->isFuture($now)) {
            throw new DomainException('Nur Genehmigungen, deren Gültigkeit in der Zukunft liegt, können storniert werden.');
        }

        $this->eventDispatcher->dispatch(new PermitCancelledEvent($permit));

        // DSGVO-konforme Anonymisierung
        $anonymizedPermit = new Permit(
            code: $permit->code,
            template_key: $permit->template_key,
            owner: new Owner('[ANONYMISIERT]', null, new PlotNumber(0)),
            vehicle: new Vehicle($permit->getVehicleType(), new LicensePlate('XXX-XX 9999'), '[ANONYMISIERT]'),
            validity: clone $permit->validity,
            status: new Status(PermitStatus::Storniert, false, 'Durch Pächter storniert'),
            erstellt: $permit->getCreatedAt(),
            interner_kommentar: $permit->getInternalComment(),
            agreements: $permit->agreements,
        );

        $this->cancelledRepository->saveCancelled($anonymizedPermit);
        $this->repository->delete($permit->code->value);
    }
}
