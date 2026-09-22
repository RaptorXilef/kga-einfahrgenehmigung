<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\FinalizePermit;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Modules\Permit\Application\UseCases\GetVerifiedRequest\GetVerifiedRequestHandler;
use App\Modules\Permit\Application\UseCases\GetVerifiedRequest\GetVerifiedRequestQuery;
use App\Modules\Permit\Domain\PermitStatus;
use Throwable;

#[Route('POST', '/api/finalize_wire')]
final readonly class FinalizeWireAction implements ViewActionInterface
{
    public function __construct(
        private GetVerifiedRequestHandler $getVerifiedHandler,
        private FinalizePermitHandler $finalizeHandler,
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
            $tempRequest = $this->getVerifiedHandler->handle(new GetVerifiedRequestQuery($dto->identifier));
            if ($tempRequest === null) {
                return JsonResponse::error('Sitzung abgelaufen oder nicht gefunden.');
            }

            $price = (float) ($tempRequest['preis'] ?? 0.0);

            $targetStatus = $price <= 0.0 ? PermitStatus::Bezahlt : PermitStatus::Offen;
            $comment = $price <= 0.0 ? 'Kostenlos / Gebührenfrei' : 'Zahlung per Überweisung gewählt';

            $permit = $this->finalizeHandler->handle(new FinalizePermitCommand(
                $dto->identifier,
                $targetStatus,
                $comment,
            ));

            return JsonResponse::success(['code' => $permit->code->value]);
        } catch (Throwable $e) {
            return JsonResponse::error($e->getMessage());
        }
    }
}
