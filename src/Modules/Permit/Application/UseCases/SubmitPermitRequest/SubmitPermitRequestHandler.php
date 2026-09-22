<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SubmitPermitRequest;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Utils\ClockInterface;
use App\Core\Event\VerificationRequestedEvent;
use App\Core\Exception\PermitCollisionException;
use App\Core\Security\Sanitizer;
use App\Core\Utils\DateRangeHelper;
use App\Modules\Permit\Application\DTO\PermitFormData;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Domain\PermitStatus;
use App\Modules\Permit\Domain\VerificationRepositoryInterface;
use App\Modules\Permit\Domain\VerificationRequest;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SubmitPermitRequestHandler
{
    public function __construct(
        private ConfigInterface $config,
        private ClockInterface $clock,
        private VerificationRepositoryInterface $verificationRepository,
        private PermitRepositoryInterface $permitRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(SubmitPermitRequestCommand $command): SubmitPermitResult
    {
        $newData = $command->formData;
        $maxPlot = (int) $this->config->get('max_plot_number', 9999);

        if ($newData->parzelle->value > $maxPlot) {
            throw new InvalidArgumentException("Die eingegebene Parzelle {$newData->parzelle->value} existiert nicht.");
        }

        $startDate = new DateTimeImmutable($newData->datumVon);
        $endDate = new DateTimeImmutable($newData->datumBis);
        $this->validateNoCollisions($newData->parzelle->value, $startDate, $endDate);

        $rawDataArray = $this->transformDtoToArray($newData);

        // --- UPDATE-MODUS (Korrektur im Formular) ---
        if ($command->editToken !== null && $command->sessionEmail !== null) {
            $allVerified = $this->verificationRepository->loadVerified();
            $oldData = isset($allVerified[$command->editToken]) ? $allVerified[$command->editToken]->data : null;

            if ($oldData !== null && Sanitizer::normalizeEmail((string) $newData->email) === Sanitizer::normalizeEmail($command->sessionEmail)) {

                $priceRelevantChanged = ($oldData['template_key'] ?? '') !== $rawDataArray['template_key']
                    || ($oldData['typ'] ?? '') !== $rawDataArray['typ']
                    || ($oldData['voucher'] ?? '') !== $rawDataArray['voucher'];

                if (!$priceRelevantChanged) {
                    $merged = \array_merge($oldData, $rawDataArray);
                    $merged['preis'] = $oldData['preis'] ?? 0;
                    $merged['status'] = PermitStatus::Offen->value;

                    $expires = $allVerified[$command->editToken]->expiresAt ?? $this->clock->now()->modify('+48 hours');
                    $allVerified[$command->editToken] = new VerificationRequest($command->editToken, $expires, $merged);
                    $this->verificationRepository->saveVerified($allVerified);

                    return new SubmitPermitResult('redirect_checkout', $command->editToken);
                }

                // Preis hat sich geändert -> Löschen und neu anlegen!
                unset($allVerified[$command->editToken]);
                $this->verificationRepository->saveVerified($allVerified);
            }
        }

        // --- NEUANLAGE MODUS ---
        $token = $this->createNewPendingRequest($rawDataArray);

        return new SubmitPermitResult('redirect_verify', $token);
    }

    private function createNewPendingRequest(array $data): string
    {
        $tKey = $data['template_key'];
        $templates = (array) $this->config->get('permit_templates', []);
        $template = $templates[$tKey] ?? $templates['std_7'] ?? ['prices' => []];

        $vehicleTypes = (array) $this->config->get('vehicle_types', []);
        $defaultType = $vehicleTypes === [] ? 'pkw' : \array_key_first($vehicleTypes);
        $typ = $data['typ'] ?? $defaultType;

        $data['preis'] = (float) ($template['prices'][$typ] ?? ($template['prices'][$defaultType] ?? 0.0));

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

    private function validateNoCollisions(int $parzelleId, DateTimeImmutable $start, DateTimeImmutable $end): void
    {
        // 1. Check in Database (Super fast via SQL!)
        if ($this->permitRepository->hasCollision($parzelleId, $start, $end)) {
            $plotFormatted = \str_pad((string) $parzelleId, 4, '0', \STR_PAD_LEFT);

            throw new PermitCollisionException("Kollision: Für Parzelle {$plotFormatted} existiert bereits eine Genehmigung im gewählten Zeitraum.");
        }

        // 2. Check in Pending/Unpaid Requests
        $allPending = $this->verificationRepository->loadPending();
        foreach ($allPending as $pendingReq) {
            $pendingData = $pendingReq->data;
            $pPlot = (int) ($pendingData['parzelle'] ?? 0);
            $pStart = new DateTimeImmutable((string) ($pendingData['datum_von'] ?? 'now'));
            $pEnd = new DateTimeImmutable((string) ($pendingData['datum_bis'] ?? 'now'));

            if ($pPlot === $parzelleId && DateRangeHelper::overlaps($pStart, $pEnd, $start, $end)) {
                $plotFormatted = \str_pad((string) $parzelleId, 4, '0', \STR_PAD_LEFT);

                throw new PermitCollisionException("Hinweis: Für Parzelle {$plotFormatted} läuft bereits eine Anfrage für diesen Zeitraum. Bitte wählen Sie andere Daten.");
            }
        }
    }

    private function transformDtoToArray(PermitFormData $dto): array
    {
        return [
            'name' => $dto->name,
            'email' => $dto->email ? (string) $dto->email : null,
            'parzelle' => (string) $dto->parzelle->value,
            'typ' => $dto->typ,
            'kennzeichen' => $dto->kennzeichen->value,
            'firma' => $dto->firma,
            'zweck' => $dto->zweck,
            'template_key' => $dto->templateKey->value,
            'datum_von' => $dto->datumVon,
            'datum_bis' => $dto->datumBis,
            'manual_price' => $dto->manualPrice->amount,
            'status' => $dto->status->value,
            'interner_kommentar' => $dto->internerKommentar,
            'agreements' => $dto->agreements,
            'voucher' => $dto->voucher ? (string) $dto->voucher : null,
        ];
    }
}
