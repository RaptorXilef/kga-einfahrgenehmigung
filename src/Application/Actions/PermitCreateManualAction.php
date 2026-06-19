<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\PermitCreateManualRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;
use App\Core\Service\PermitService;

/**
 * Action zur manuellen Ausstellung einer Genehmigung (ohne Zahlungsfluss).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitCreateManualAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private PermitService $permitService
    ) {
    }

    /**
     * Erstellt eine Genehmigung ohne vorangegangenen automatisierten Bezahlprozess.
     *
     * Erzwingt 'status' = 'bezahlt' und nutzt PermitService::createPermit().
     *
     * @param array<string, mixed> $post
     *
     * @return string Bestätigung mit dem generierten Genehmigungscode.
     */
    public function execute(array $post): mixed
    {
        if (! $this->auth->hasPermission('dashboard.generator-tools.manual_permit.execute')) {
            return 'Fehler: Sie haben keine Berechtigung, manuelle Genehmigungen zu erstellen.';
        }

        try {
            $dto = PermitCreateManualRequest::fromArray($post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        try {
            // Wir übergeben das sauber gefilterte Array aus dem DTO an den Service
            $this->permitService->createPermit($dto->rawSanitized, $dto->sendEmail);
            return 'Manuelle Genehmigung wurde erfolgreich erstellt.';
        } catch (\Exception $e) {
            return 'Fehler: ' . $e->getMessage();
        }
    }
}
