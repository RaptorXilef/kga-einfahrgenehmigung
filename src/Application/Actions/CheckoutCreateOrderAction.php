<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Core\Service\PermitService;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/CheckoutCreateOrderAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class CheckoutCreateOrderAction implements ViewActionInterface
{
    public function __construct(private PermitService $permitService, private PaymentProviderInterface $payment)
    {
    }

    public function execute(array $requestData): void
    {
        try {
            $dto = SimpleIdentifierRequest::fromArray($requestData['post'], 'token');
        } catch (ValidationException $e) {
            JsonResponse::error($e->getMessage());

            return;
        }

        try {
            $tempRequest = $this->permitService->getVerifiedRequest($dto->identifier);
            if ($tempRequest === null) {
                throw new \Exception('Sitzung nicht gefunden oder abgelaufen');
            }

            $orderId = $this->payment->createOrder((float) $tempRequest['preis']);
            if ($orderId) {
                JsonResponse::success(['id' => $orderId]);
            } else {
                JsonResponse::error('PayPal Error', 500);
            }
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }
    }
}
