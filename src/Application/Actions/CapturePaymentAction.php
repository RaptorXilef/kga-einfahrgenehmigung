<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\CapturePaymentRequest;
use App\Application\Exception\ValidationException;
use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Core\Service\PermitService;

/**
 * Action zur Abwicklung und Erfassung externer Zahlungen (PayPal-Capture).
 *
 * Path: src/Application/Actions/CapturePaymentAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class CapturePaymentAction implements ViewActionInterface
{
    public function __construct(
        private PermitService $permitService,
        private PaymentProviderInterface $paymentProvider,
    ) {
    }

    public function execute(array $requestData): void
    {
        try {
            $dto = CapturePaymentRequest::fromArray($requestData['input']);
        } catch (ValidationException $exception) {
            JsonResponse::error($exception->getMessage(), 400);

            return;
        }

        try {
            // ORCHESTRIERUNG: Die Action steuert jetzt die Abläufe, nicht mehr der Service!
            $tempRequest = $this->permitService->getVerifiedRequest($dto->token);

            if ($tempRequest === null) {
                JsonResponse::error('Sitzung nicht gefunden oder abgelaufen', 400);

                return;
            }

            // Zahlung ausführen
            if ($this->paymentProvider->captureOrder($dto->orderId, (float) $tempRequest['preis'])) {
                // Bei Erfolg: Den Service anweisen, die Genehmigung zu finalisieren
                $this->permitService->finaliseRequest($dto->token, 'bezahlt', 'Bezahlt via PayPal');
                JsonResponse::success(['message' => 'Zahlung verarbeitet und Antrag finalisiert']);
            } else {
                JsonResponse::error('Fehler bei Verifizierung der Zahlung', 400);
            }
        } catch (\Exception $exception) {
            JsonResponse::error($exception->getMessage(), 400);
        }
    }
}
