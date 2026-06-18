<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\CapturePaymentRequest;
use App\Application\Exception\ValidationException;
use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
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
    ) {
    }

    public function execute(array $requestData): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            JsonResponse::error('Methode nicht erlaubt.', 405);
        }

        try {
            $dto = CapturePaymentRequest::fromArray($requestData['input']);
        } catch (ValidationException $exception) {
            JsonResponse::error($exception->getMessage(), 400);

            return;
        }

        try {
            if ($this->permitService->completePayment(
                $dto->token,
                $dto->orderId,
            )) {
                JsonResponse::success(['message' => 'Zahlung verarbeitet und Antrag finalisiert']);
            } else {
                JsonResponse::error('Fehler bei Verifizierung');
            }
        } catch (\Exception $exception) {
            JsonResponse::error($exception->getMessage(), 400);
        }
    }
}
