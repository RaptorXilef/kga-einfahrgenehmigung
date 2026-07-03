<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Entity\PermitStatus;
use App\Core\Service\PermitService;

/**
 * Action zum finalisieren eines Antrags via klassischer Banküberweisung.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('finalize_wire')]
final readonly class CheckoutFinalizeWireAction implements ViewActionInterface
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
            $permit = $this->permitService->finaliseRequest(
                $dto->identifier,
                PermitStatus::Offen,
                'Zahlung per Überweisung gewählt',
            );

            return JsonResponse::success(['code' => $permit->code]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage());
        }
    }
}
