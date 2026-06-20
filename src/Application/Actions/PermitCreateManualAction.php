<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\PermitCreateManualRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\PermitService;

/**
 * Action zur manuellen Ausstellung einer Genehmigung (ohne Zahlungsfluss).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitCreateManualAction implements ActionInterface
{
    public function __construct(
        private PermitService $permitService,
    ) {
    }

    /**
     * Erstellt eine Genehmigung ohne vorangegangenen automatisierten Bezahlprozess.
     *
     * Erzwingt 'status' = 'bezahlt' und nutzt PermitService::createPermit().
     *
     * @return string Bestätigung mit dem generierten Genehmigungscode.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = PermitCreateManualRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        try {
            // Wir übergeben das sauber gefilterte Array aus dem DTO an den Service
            $this->permitService->createPermit($dto->formData, $dto->sendEmail);

            return 'Manuelle Genehmigung wurde erfolgreich erstellt.';
        } catch (\Exception $e) {
            return 'Fehler: ' . $e->getMessage();
        }
    }
}
