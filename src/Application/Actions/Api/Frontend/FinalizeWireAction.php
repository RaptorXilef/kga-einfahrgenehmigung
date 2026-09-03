<?php

declare(strict_types=1);

namespace App\Application\Actions\Api\Frontend;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Core\Entity\PermitStatus;
use App\Core\Service\PermitService;
use Throwable;

/**
 * Action zum finalisieren eines Antrags via klassischer Banküberweisung oder Kostenlos-Abschluss.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('POST', '/api/finalize_wire')]
final readonly class FinalizeWireAction implements ViewActionInterface
{
    public function __construct(
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
            // FIX: Preis des Antrags prüfen, um intelligent den Status zu setzen
            $tempRequest = $this->permitService->getVerifiedRequest($dto->identifier);
            if ($tempRequest === null) {
                return JsonResponse::error('Sitzung abgelaufen oder nicht gefunden.');
            }

            $price = (float) ($tempRequest['preis'] ?? 0.0);

            // Wenn der Betrag 0 ist, ist die Genehmigung sofort bezahlt/gültig!
            $targetStatus = $price <= 0.0 ? PermitStatus::Bezahlt : PermitStatus::Offen;
            $comment = $price <= 0.0 ? 'Kostenlos / Gebührenfrei' : 'Zahlung per Überweisung gewählt';

            $permit = $this->permitService->finaliseRequest(
                $dto->identifier,
                $targetStatus,
                $comment,
            );

            return JsonResponse::success(['code' => $permit->code->value]);
        } catch (Throwable $e) {
            return JsonResponse::error($e->getMessage());
        }
    }
}
