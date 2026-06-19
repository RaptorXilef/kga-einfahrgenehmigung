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
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class CheckoutCreateOrderAction implements ViewActionInterface
{
    public function __construct(private PermitService $permitService, private PaymentProviderInterface $payment)
    {
    }

    public function execute(array $requestData): mixed
    {
        try {
            $dto = SimpleIdentifierRequest::fromArray($requestData['post'], 'token');
        } catch (ValidationException $e) {
            JsonResponse::error($e->getMessage());

            return null;
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

        return null;
    }
}
