<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\ApiPerformUpdateRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\System\SystemUpdaterInterface;
use App\Core\Service\AuditLoggerService;

/**
 * Action zum Entpacken und Anwenden eines System-Updates (Phase 1).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('perform_update')]
final readonly class SystemPerformUpdateAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SystemUpdaterInterface $updater,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.update.execute';
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
            $this->auditLogger->log('SYSTEM_UPDATE_PERFORM', 'System-Dateien aus ZIP-Archiv aktualisiert.');

            return JsonResponse::success(['message' => 'Update erfolgreich installiert!']);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage());
        }
    }
}
