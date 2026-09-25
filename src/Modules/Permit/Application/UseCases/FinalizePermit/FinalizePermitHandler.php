<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\FinalizePermit;

use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Storage\LockManagerInterface;
use App\Modules\Permit\Domain\Events\PermitCreatedEvent;
use App\Modules\Permit\Domain\Owner;
use App\Modules\Permit\Domain\PermitFactory;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Domain\Vehicle;
use App\Modules\Permit\Domain\VerificationRepositoryInterface;
use App\SharedKernel\Application\Command\CommandWithResultHandlerInterface;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use App\SharedKernel\Domain\ValueObject\Price;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use DateTimeImmutable;
use Override;
use RuntimeException;

/**
 * @implements CommandWithResultHandlerInterface<FinalizePermitCommand, string>
 */
final readonly class FinalizePermitHandler implements CommandWithResultHandlerInterface
{
    public function __construct(
        private LockManagerInterface $lockManager,
        private VerificationRepositoryInterface $verificationRepository,
        private PermitRepositoryInterface $permitRepository,
        private PermitFactory $permitFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param FinalizePermitCommand $command
     */
    #[Override]
    public function handle(mixed $command): string
    {
        return $this->lockManager->executeWithLock('checkout', function () use ($command): string {
            $allVerified = $this->verificationRepository->loadVerified();

            if (!isset($allVerified[$command->token])) {
                throw new RuntimeException('Antragssitzung abgelaufen oder bereits abgeschlossen.');
            }

            $data = $allVerified[$command->token]->data;

            $startDate = new DateTimeImmutable($data['datum_von']);
            $customEndDate = !empty($data['datum_bis']) ? new DateTimeImmutable($data['datum_bis']) : null;
            $emailStr = \trim((string) ($data['email'] ?? ''));

            $permit = $this->permitFactory->createNew(
                templateKey: new TemplateKey($data['template_key']),
                owner: new Owner(\strip_tags($data['name']), $emailStr !== '' ? new EmailAddress($emailStr) : null, new PlotNumber($data['parzelle'])),
                vehicle: new Vehicle($data['typ'] ?? 'pkw', new LicensePlate($data['kennzeichen']), $data['firma'] ? \strip_tags($data['firma']) : null),
                startDate: $startDate,
                customEndDate: $customEndDate,
                price: new Price((float) ($data['preis'] ?? 0.0)),
                purpose: $data['zweck'] ?? '',
                status: $command->status,
                internerKommentar: $command->comment,
                agreements: $data['agreements'] ?? [],
            );

            $this->permitRepository->save($permit);

            // Housekeeping: Remove from pending
            unset($allVerified[$command->token]);
            $this->verificationRepository->saveVerified($allVerified);

            // Mails feuern!
            $codeParts = \explode('-', $permit->code->value);
            $shortCode = \end($codeParts);
            $this->eventDispatcher->dispatch(new PermitCreatedEvent($permit, $shortCode));

            return $permit->code->value;
        });
    }
}
