<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SubmitPermitRequest;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Application\DTO\PermitFormData;
use App\Modules\Permit\Domain\DateRangeHelper;
use App\Modules\Permit\Domain\Events\VerificationRequestedEvent;
use App\Modules\Permit\Domain\Exceptions\PermitCollisionException;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Domain\PermitStatus;
use App\Modules\Permit\Domain\VerificationRepositoryInterface;
use App\Modules\Permit\Domain\VerificationRequest;
use App\SharedKernel\Application\Security\Sanitizer;
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

        // --- BUGFIX: UPDATE-MODUS (Korrektur im Formular) ---
        if ($command->editToken !== null && $command->sessionEmail !== null) {
            $allVerified = $this->verificationRepository->loadVerified();
            $oldData = isset($allVerified[$command->editToken]) ? $allVerified[$command->editToken]->data : null;

            // Wenn die E-Mail NICHT geändert wurde -> Nur Daten updaten & direkt zurück zum Checkout!
            if ($oldData !== null && Sanitizer::normalizeEmail((string) $newData->email) === Sanitizer::normalizeEmail($command->sessionEmail)) {

                // Wir mergen die neuen Daten in die alten (damit verification_code erhalten bleibt)
                $merged = \array_merge($oldData, $rawDataArray);

                // Preis dynamisch neu berechnen (falls sich Tarif oder Fahrzeugtyp geändert hat)
                $tKey = $merged['template_key'];
                $templates = (array) $this->config->get('permit_templates', []);
                $template = $templates[$tKey] ?? $templates['std_7'] ?? ['prices' => []];

                $vehicleTypes = (array) $this->config->get('vehicle_types', []);
                $defaultType = $vehicleTypes === [] ? 'pkw' : \array_key_first($vehicleTypes);
                $typ = $merged['typ'] ?? $defaultType;

                $merged['preis'] = (float) ($template['prices'][$typ] ?? ($template['prices'][$defaultType] ?? 0.0));
                $merged['status'] = PermitStatus::Offen->value;

                $expires = $allVerified[$command->editToken]->expiresAt ?? $this->clock->now()->modify('+48 hours');
                $allVerified[$command->editToken] = new VerificationRequest($command->editToken, $expires, $merged);

                $this->verificationRepository->saveVerified($allVerified);

                return new SubmitPermitResult('redirect_checkout', $command->editToken);
            }

            // Falls die E-Mail geändert WURDE, löschen wir das alte Token,
            // damit unten regulär eine neue Bestätigungs-Mail rausgeht.
            if ($oldData !== null) {
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
        if ($this->permitRepository->hasCollision($parzelleId, $start, $end)) {
            $plotFormatted = \str_pad((string) $parzelleId, 4, '0', \STR_PAD_LEFT);

            throw new PermitCollisionException("Kollision: Für Parzelle {$plotFormatted} existiert bereits eine Genehmigung im gewählten Zeitraum.");
        }

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
