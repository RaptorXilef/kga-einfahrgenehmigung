<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Core\Service\PermitService;

/**
 * Action zur Erstellung einer Zahlungs-Order (PayPal) aus dem Checkout.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('create_order')]
final readonly class CheckoutCreateOrderAction implements ViewActionInterface
{
    public function __construct(
        private PaymentProviderInterface $payment,
        private PermitService $permitService,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleIdentifierRequest::fromArray($request->post, 'token');
        } catch (ValidationException $e) {
            return JsonResponse::error($e->getMessage());
        }

        try {
            $tempRequest = $this->permitService->getVerifiedRequest($dto->identifier);
            if ($tempRequest === null) {
                throw new \Exception('Sitzung nicht gefunden oder abgelaufen');
            }

            $orderId = $this->payment->createOrder((float) $tempRequest['preis']);
            if ($orderId) {
                return JsonResponse::success(['id' => $orderId]);
            }

            return JsonResponse::error('PayPal Error', 500);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage());
        }
    }
}
