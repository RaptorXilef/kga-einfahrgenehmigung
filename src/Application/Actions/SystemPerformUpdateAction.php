<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ApiPerformUpdateRequest;
use App\Application\Exception\ValidationException;
use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Infrastructure\Maintenance\GitHubUpdaterService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class SystemPerformUpdateAction implements ViewActionInterface
{
    public function __construct(private GitHubUpdaterService $updater)
    {
    }

    public function execute(array $requestData): mixed
    {
        try {
            $dto = ApiPerformUpdateRequest::fromArray($requestData['input']);
        } catch (ValidationException $e) {
            JsonResponse::error($e->getMessage(), 400);

            return null;
        }

        try {
            $this->updater->performUpdate($dto->zipUrl);
            JsonResponse::success(['message' => 'Update erfolgreich installiert!']);
        } catch (\Throwable $e) {
            JsonResponse::error($e->getMessage());
        }

        return null;
    }
}
