<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\SystemMaintenanceRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Infrastructure\Maintenance\MigrationService;

/**
 * Action zum rigorosen Löschen aller Daten eines bestimmten Speicher-Ziels.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('truncate_target')]
final readonly class SystemTruncateTargetAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private MigrationService $migrationService,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'dashboard.migration.delete-data.execute';
    }

    /**
     * Löscht alle Daten eines bestimmten Speicher-Ziels rigoros (Truncate).
     * Wird für administrative System-Resets oder vor großen Migrationen verwendet.
     *
     * @return string Statusmeldung über die Löschung.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SystemMaintenanceRequest::forTruncate($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin.php');
        }

        $msg = $this->migrationService->truncateTarget($dto->target, $dto->engine);
        $this->sessionManager->addFlash('success', $msg);

        return new RedirectResponse('admin.php');
    }
}
