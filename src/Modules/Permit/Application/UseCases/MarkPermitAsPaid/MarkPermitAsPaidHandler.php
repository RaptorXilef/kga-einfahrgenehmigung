<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\MarkPermitAsPaid;

use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use DateTimeImmutable;
use DomainException;

/**
 * @implements CommandHandlerInterface<MarkPermitAsPaidCommand>
 */
final readonly class MarkPermitAsPaidHandler implements CommandHandlerInterface
{
    public function __construct(
        private PermitRepositoryInterface $repository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param MarkPermitAsPaidCommand $command
     * @throws DomainException Wenn das Permit nicht gefunden wird.
     */
    public function handle(mixed $command): void
    {
        $permit = $this->repository->findByCode($command->code);

        if ($permit === null) {
            throw new DomainException("Genehmigung {$command->code} nicht gefunden.");
        }

        // Datum parsen
        $dtBezahltAm = null;
        if ($command->bookingDate) {
            $dateObj = DateTimeImmutable::createFromFormat('d.m.y', \trim($command->bookingDate));
            if ($dateObj === false) {
                $dateObj = DateTimeImmutable::createFromFormat('d.m.Y', \trim($command->bookingDate));
            }
            $dtBezahltAm = $dateObj !== false ? $dateObj : $this->clock->now();
        } else {
            $dtBezahltAm = $this->clock->now();
        }

        $aktuellerKommentar = $permit->interner_kommentar ?? '';
        $neuerKommentar = $aktuellerKommentar;

        if ($command->reason !== null && !\str_contains($aktuellerKommentar, $command->reason)) {
            $neuerKommentar = $aktuellerKommentar !== '' ? $aktuellerKommentar . ' | ' . $command->reason : $command->reason;
        }

        // Wir verändern den Zustand der Domänen-Entität über Methoden, nicht über Konstruktor-Magie!
        // Hier greifen wir temporär in die "interner_kommentar" Eigenschaft ein (via Reflection oder Neuzuweisung im Repo)
        // Aber die Domain Methode `markAsPaid` regelt den Hauptstatus:
        $permit->markAsPaid($neuerKommentar, $dtBezahltAm);

        $this->repository->save($permit);
    }
}
