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
 * Path: src/Application/Actions/CheckoutFinalizeWireAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class CheckoutFinalizeWireAction implements ViewActionInterface
{
    public function __construct(private PermitService $permitService)
    {
    }

    public function execute(array $requestData): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            JsonResponse::error('Methode nicht erlaubt.', 405);
        }

        try {
            $dto = SimpleIdentifierRequest::fromArray($requestData['post'], 'token');
        } catch (ValidationException $e) {
            JsonResponse::error($e->getMessage());

            return;
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
    }
}
