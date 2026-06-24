<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\PermitCreateManualRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Core\Service\PermitService;

/**
 * Action zur manuellen Ausstellung einer Genehmigung (ohne Zahlungsfluss).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitCreateManualAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private PermitService $permitService,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'dashboard.generator-tools.direct_issue.execute';
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
            return new RedirectResponse('admin.php?msg=' . \urlencode($e->getMessage()));
        }

        try {
            $this->permitService->createPermit($dto->formData, $dto->sendEmail);

            return new RedirectResponse('admin.php?msg=' . \urlencode('Manuelle Genehmigung wurde erfolgreich erstellt.'));
        } catch (\Exception $e) {
            return new RedirectResponse('admin.php?msg=' . \urlencode('Fehler: ' . $e->getMessage()));
        }
    }
}
