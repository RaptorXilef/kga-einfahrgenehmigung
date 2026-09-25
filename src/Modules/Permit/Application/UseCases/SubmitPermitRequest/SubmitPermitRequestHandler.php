<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SubmitPermitRequest;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\DateRangeHelper;
use App\Modules\Permit\Domain\Events\VerificationRequestedEvent;
use App\Modules\Permit\Domain\Exceptions\PermitCollisionException;
use App\Modules\Permit\Domain\PermitFinancialCalculator;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Domain\PermitStatus;
use App\Modules\Permit\Domain\VerificationRepositoryInterface;
use App\Modules\Permit\Domain\VerificationRequest;
use App\SharedKernel\Application\Command\CommandWithResultHandlerInterface;
use App\SharedKernel\Application\Security\Sanitizer;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use App\SharedKernel\Domain\ValueObject\VoucherCode;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;

/**
 * Da der Handler den generierten Token-String (ID) für den Controller-Flow zurückgeben muss,
 * verzichten wir pragmatisch auf das strikte (void) CommandHandlerInterface.
 *
 * @implements CommandWithResultHandlerInterface<SubmitPermitRequestCommand, string>
 */
final readonly class SubmitPermitRequestHandler implements CommandWithResultHandlerInterface
{
    public function __construct(
        private ConfigInterface $config,
        private ClockInterface $clock,
        private VerificationRepositoryInterface $verificationRepository,
        private PermitRepositoryInterface $permitRepository,
        private EventDispatcherInterface $eventDispatcher,
        private PermitFinancialCalculator $financialCalculator,
    ) {
    }

    /**
     * @param SubmitPermitRequestCommand $command
     */
    #[Override]
    public function handle(mixed $command): string
    {
        $maxPlot = (int) $this->config->get('max_plot_number', 9999);

        if ($command->parzelle->value > $maxPlot) {
            throw new InvalidArgumentException("Die eingegebene Parzelle {$command->parzelle->value} existiert nicht.");
        }

        $startDate = new DateTimeImmutable($command->datumVon);
        $endDate = new DateTimeImmutable($command->datumBis);
        $this->validateNoCollisions($command->parzelle->value, $startDate, $endDate, $command->kennzeichen->value, $command->firma);

        $rawDataArray = $this->extractDataFromCommand($command);

        // --- UPDATE-MODUS (Korrektur im Formular) ---
        if ($command->editToken !== null && $command->sessionEmail !== null) {
            $allVerified = $this->verificationRepository->loadVerified();
            $oldData = isset($allVerified[$command->editToken]) ? $allVerified[$command->editToken]->data : null;

            // Wenn die E-Mail NICHT geändert wurde -> Nur Daten updaten & Token für Checkout zurückgeben
            if ($oldData !== null && Sanitizer::normalizeEmail((string) $command->email) === Sanitizer::normalizeEmail($command->sessionEmail)) {
                $merged = \array_merge($oldData, $rawDataArray);

                $typ = $merged['typ'] ?? 'pkw';
                $merged['preis'] = $this->financialCalculator->calculateBasePrice(new TemplateKey($merged['template_key']), $typ);
                $merged['status'] = PermitStatus::Offen->value;

                $expires = $allVerified[$command->editToken]->expiresAt ?? $this->clock->now()->modify('+48 hours');
                $allVerified[$command->editToken] = new VerificationRequest($command->editToken, $expires, $merged);

                $this->verificationRepository->saveVerified($allVerified);

                return $command->editToken;
            }

            // Falls die E-Mail geändert WURDE, löschen wir das alte Token,
            // damit unten regulär eine neue Bestätigungs-Mail rausgeht.
            if ($oldData !== null) {
                unset($allVerified[$command->editToken]);
                $this->verificationRepository->saveVerified($allVerified);
            }
        }

        // --- NEUANLAGE MODUS ---
        return $this->createNewPendingRequest($rawDataArray);
    }

    private function createNewPendingRequest(array $data): string
    {
        $typ = $data['typ'] ?? 'pkw';
        $data['preis'] = $this->financialCalculator->calculateBasePrice(new TemplateKey($data['template_key']), $typ);

        $token = \bin2hex(\random_bytes(32));
        $shortCode = \strtoupper(\substr(\bin2hex(\random_bytes(4)), 0, 6));

        $data['verification_token'] = $token;
        $data['verification_code'] = $shortCode;

        $hours = (int) $this->config->get('hours_pending_verify', 24);
        $expires = $this->clock->now()->modify("+{$hours} hours");

        $req = new VerificationRequest($token, $expires, $data);
        $allPending = $this->verificationRepository->loadPending();
        $allPending[$token] = $req;
        $this->verificationRepository->savePending($allPending);

        $this->eventDispatcher->dispatch(new VerificationRequestedEvent($data, $token, $shortCode));

        return $token;
    }

    private function extractDataFromCommand(SubmitPermitRequestCommand $command): array
    {
        return [
            'name' => $command->name,
            'email' => $command->email instanceof EmailAddress ? (string) $command->email : null,
            'parzelle' => (string) $command->parzelle->value,
            'typ' => $command->typ,
            'kennzeichen' => $command->kennzeichen->value,
            'firma' => $command->firma,
            'zweck' => $command->zweck,
            'template_key' => $command->templateKey->value,
            'datum_von' => $command->datumVon,
            'datum_bis' => $command->datumBis,
            'status' => 'offen',
            'interner_kommentar' => null,
            'agreements' => $command->agreements,
            'voucher' => $command->voucher instanceof VoucherCode ? (string) $command->voucher : null,
        ];
    }

    private function validateNoCollisions(int $parzelleId, DateTimeImmutable $start, DateTimeImmutable $end, string $licensePlate, ?string $company): void
    {
        if ($this->permitRepository->hasCollision($parzelleId, $start, $end, $licensePlate, $company)) {
            $plotFormatted = \str_pad((string) $parzelleId, 4, '0', \STR_PAD_LEFT);

            throw new PermitCollisionException("Kollision: Für dieses Kennzeichen oder diese Firma existiert auf Parzelle {$plotFormatted} bereits eine Genehmigung im gewählten Zeitraum.");
        }

        $allPending = $this->verificationRepository->loadPending();
        $searchPlate = \preg_replace('/[^A-Z0-9]/', '', \strtoupper($licensePlate));
        $todayStr = $this->clock->now()->format('Y-m-d');

        foreach ($allPending as $pendingReq) {
            $pendingData = $pendingReq->data;
            $pPlot = (int) ($pendingData['parzelle'] ?? 0);
            $pStart = new DateTimeImmutable((string) ($pendingData['datum_von'] ?? $todayStr));
            $pEnd = new DateTimeImmutable((string) ($pendingData['datum_bis'] ?? $todayStr));

            if ($pPlot !== $parzelleId || !DateRangeHelper::overlaps($pStart, $pEnd, $start, $end)) {
                continue;
            }

            $pKennzeichen = \preg_replace('/[^A-Z0-9]/', '', \strtoupper((string) ($pendingData['kennzeichen'] ?? '')));
            $pFirma = \trim((string) ($pendingData['firma'] ?? ''));

            $isSamePlate = $searchPlate !== '' && $searchPlate !== 'XXXXX9999' && $searchPlate === $pKennzeichen;
            $isSameCompany = $company !== null && $company !== '' && $company === $pFirma;

            if ($isSamePlate || $isSameCompany) {
                $plotFormatted = \str_pad((string) $parzelleId, 4, '0', \STR_PAD_LEFT);

                throw new PermitCollisionException("Hinweis: Für dieses Kennzeichen oder diese Firma läuft auf Parzelle {$plotFormatted} bereits eine Anfrage für diesen Zeitraum.");
            }
        }
    }
}
