<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\PermitService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class CheckoutFinalizeWireAction implements ViewActionInterface
{
    public function __construct(private PermitService $permitService)
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
            $permit = $this->permitService->finaliseRequest(
                $dto->identifier,
                'offen',
                'Zahlung per Überweisung gewählt',
            );
            JsonResponse::success(['code' => $permit->code]);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        return null;
    }
}
