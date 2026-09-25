<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\CreateVoucher;

use App\Contracts\Utils\ClockInterface;
use App\Modules\Voucher\Domain\Voucher;
use App\Modules\Voucher\Domain\VoucherRepositoryInterface;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use App\SharedKernel\Application\Command\CommandInterface;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Override;

/**
 * Handler für die Gutscheinerstellung.
 * Sorgt für Validierung, ID-Generierung, Domain-Instanziierung und Persistenz.
 *
 * @implements CommandHandlerInterface<CreateVoucherCommand>
 */
final readonly class CreateVoucherHandler implements CommandHandlerInterface
{
    public function __construct(
        private VoucherRepositoryInterface $repository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param CreateVoucherCommand $command
     */
    #[Override]
    public function handle(CommandInterface $command): void
    {
        // 1. Validierung
        if ($command->value < 0) {
            throw new InvalidArgumentException('Der Rabattwert darf nicht negativ sein.');
        }

        $expiresAtDate = null;
        if ($command->expiresAt !== null && $command->expiresAt !== '') {
            $expiresAtDate = new DateTimeImmutable($command->expiresAt);
        }

        // 2. Code Generierung oder Zuweisung
        $code = $command->customCode !== ''
            ? \strtoupper(\trim($command->customCode))
            : $this->generateUniqueCode();

        // 3. Domain Prüfung: Existiert der Code schon?
        if ($this->repository->findByCode($code) instanceof Voucher) {
            throw new DomainException("Der Gutscheincode '{$code}' existiert bereits.");
        }

        // 4. Aggregat erstellen (Domain)
        $voucher = Voucher::create(
            $code,
            $command->templateKey,
            $command->reason,
            $command->type,
            $command->value,
            $command->isMultiUse,
            $command->maxUses,
            $expiresAtDate,
            $command->prefillData,
            $command->createdBy,
            $this->clock->now(),
        );

        // 5. Speichern
        $this->repository->save($voucher);
    }

    /**
     * Generiert einen zufälligen, sicheren Code (z.B. V-8A2F-9B1D).
     */
    private function generateUniqueCode(): string
    {
        do {
            $random1 = \strtoupper(\substr(\bin2hex(\random_bytes(2)), 0, 4));
            $random2 = \strtoupper(\substr(\bin2hex(\random_bytes(2)), 0, 4));
            $code = "V-{$random1}-{$random2}";
        } while ($this->repository->findByCode($code) instanceof Voucher);

        return $code;
    }
}
