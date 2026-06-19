<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SystemMaintenanceRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;
use App\Infrastructure\Maintenance\MigrationService;

/**
 * Action zum rigorosen Löschen aller Daten eines bestimmten Speicher-Ziels.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemTruncateTargetAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private MigrationService $migrationService,
    ) {
    }

    /**
     * Löscht alle Daten eines bestimmten Speicher-Ziels rigoros (Truncate).
     * Wird für administrative System-Resets oder vor großen Migrationen verwendet.
     *
     * @param array<string, mixed> $post Formulardaten mit Zielbereich (target) und Speicher-Engine (engine).
     *
     * @return string Statusmeldung über die Löschung.
     */
    public function execute(array $post): mixed
    {
        try {
            $dto = SystemMaintenanceRequest::forTruncate($post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        return $this->migrationService->truncateTarget($dto->target, $dto->engine);
    }
}
