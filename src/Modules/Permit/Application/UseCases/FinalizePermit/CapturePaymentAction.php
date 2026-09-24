<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\FinalizePermit;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Modules\Permit\Application\UseCases\GetVerifiedRequest\GetVerifiedRequestHandler;
use App\Modules\Permit\Application\UseCases\GetVerifiedRequest\GetVerifiedRequestQuery;
use App\Modules\Permit\Domain\PermitStatus;
use Exception;
use Override;

#[Route('POST', '/api/capture')]
final readonly class CapturePaymentAction implements ViewActionInterface
{
    public function __construct(
        private PaymentProviderInterface $paymentProvider,
        private GetVerifiedRequestHandler $getVerifiedHandler,
        private FinalizePermitHandler $finalizeHandler,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = CapturePaymentRequest::fromArray($request->input);
        } catch (ValidationException $exception) {
            return JsonResponse::error($exception->getMessage(), 400);
        }

        try {
            $tempRequest = $this->getVerifiedHandler->handle(new GetVerifiedRequestQuery($dto->token));

            if ($tempRequest === null) {
                return JsonResponse::error('Sitzung nicht gefunden oder abgelaufen', 400);
            }

            if ($this->paymentProvider->captureOrder($dto->orderId, (float) $tempRequest['preis'])) {
                $this->finalizeHandler->handle(new FinalizePermitCommand($dto->token, PermitStatus::Bezahlt, 'Bezahlt via PayPal'));

                return JsonResponse::success(['message' => 'Zahlung verarbeitet und Antrag finalisiert']);
            }

            return JsonResponse::error('Fehler bei Verifizierung der Zahlung', 400);
        } catch (Exception $exception) {
            return JsonResponse::error($exception->getMessage(), 400);
        }
    }
}
