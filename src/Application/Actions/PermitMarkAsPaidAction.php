<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Core\Service\PermitService;

/**
 * Action zum manuellen Markieren einer Genehmigung als 'bezahlt'.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitMarkAsPaidAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private PermitService $permitService,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'dashboard.finance.mark_paid';
    }

    /**
     * Markiert eine Genehmigung manuell als bezahlt im Storage.
     *
     * Nutzt PermitService::manualActivate().
     *
     * @return string Erfolgsmeldung oder leerer String bei Fehler.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleIdentifierRequest::fromArray($request->post, 'code');
        } catch (ValidationException $e) {
            return new RedirectResponse('admin.php?msg=' . \urlencode($e->getMessage()));
        }
        $msg = $this->permitService->manualActivate($dto->identifier) ? "Zahlung für {$dto->identifier} bestätigt." : 'Fehler: Genehmigung nicht gefunden oder bereits bezahlt.';

        return new RedirectResponse('admin.php?msg=' . \urlencode($msg));
    }
}
