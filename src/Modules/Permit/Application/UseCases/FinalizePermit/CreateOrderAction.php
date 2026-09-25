<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\FinalizePermit;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Modules\Permit\Application\UseCases\GetVerifiedRequest\GetVerifiedRequestHandler;
use App\Modules\Permit\Application\UseCases\GetVerifiedRequest\GetVerifiedRequestQuery;
use Exception;
use Override;
use Throwable;

#[Route('POST', '/api/create_order')]
final readonly class CreateOrderAction implements ActionInterface
{
    public function __construct(
        private PaymentProviderInterface $payment,
        private GetVerifiedRequestHandler $getVerifiedHandler,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = CreateOrderRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            return JsonResponse::error($e->getMessage());
        }

        try {
            $tempRequest = $this->getVerifiedHandler->handle(new GetVerifiedRequestQuery($dto->token));
            if ($tempRequest === null) {
                throw new Exception('Sitzung nicht gefunden oder abgelaufen');
            }

            $orderId = $this->payment->createOrder((float) $tempRequest['preis']);

            if ($orderId) {
                return JsonResponse::success(['id' => $orderId]);
            }

            return JsonResponse::error('PayPal Error', 500);
        } catch (Throwable $e) {
            return JsonResponse::error($e->getMessage());
        }
    }
}
