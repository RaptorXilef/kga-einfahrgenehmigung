<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\FinalizePermit;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Modules\Permit\Application\UseCases\GetVerifiedRequest\GetVerifiedRequestHandler;
use App\Modules\Permit\Application\UseCases\GetVerifiedRequest\GetVerifiedRequestQuery;
use App\Modules\Permit\Domain\PermitStatus;
use Override;
use Throwable;

#[Route('POST', '/api/finalize_wire')]
final readonly class FinalizeWireAction implements ViewActionInterface
{
    public function __construct(
        private GetVerifiedRequestHandler $getVerifiedHandler,
        private FinalizePermitHandler $finalizeHandler,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = FinalizeWireRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            return JsonResponse::error($e->getMessage());
        }

        try {
            $tempRequest = $this->getVerifiedHandler->handle(new GetVerifiedRequestQuery($dto->token));
            if ($tempRequest === null) {
                return JsonResponse::error('Sitzung abgelaufen oder nicht gefunden.');
            }

            $price = (float) ($tempRequest['preis'] ?? 0.0);

            $targetStatus = $price <= 0.0 ? PermitStatus::Bezahlt : PermitStatus::Offen;
            $comment = $price <= 0.0 ? 'Kostenlos / Gebührenfrei' : 'Zahlung per Überweisung gewählt';

            $permitCode = $this->finalizeHandler->handle(new FinalizePermitCommand(
                $dto->token,
                $targetStatus,
                $comment,
            ));

            return JsonResponse::success(['code' => $permitCode]);
        } catch (Throwable $e) {
            return JsonResponse::error($e->getMessage());
        }
    }
}
