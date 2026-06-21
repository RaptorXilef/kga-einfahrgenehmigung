<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ApiPerformUpdateRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Infrastructure\Maintenance\GitHubUpdaterService;

/**
 * Action zum Entpacken und Anwenden eines System-Updates (Phase 1).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemPerformUpdateAction implements ViewActionInterface
{
    public function __construct(
        private GitHubUpdaterService $updater,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        // FIX: Klasse explizit in den RAM laden, um Autoloader-Abstürze
        // während des Live-Austauschs von Systemdateien zu verhindern!
        \class_exists(JsonResponse::class);

        try {
            $dto = ApiPerformUpdateRequest::fromArray($request->input);
        } catch (ValidationException $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }

        try {
            $this->updater->performUpdate($dto->zipUrl);

            return JsonResponse::success(['message' => 'Update erfolgreich installiert!']);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage());
        }
    }
}
