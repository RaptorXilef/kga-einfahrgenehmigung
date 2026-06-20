<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\PermitService;

/**
 * Action zum manuellen Markieren einer Genehmigung als 'bezahlt'.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitMarkAsPaidAction implements ActionInterface
{
    public function __construct(
        private PermitService $permitService,
    ) {
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
            // Hinweis: Das Formular übergibt 'code', nicht 'id'
            $dto = SimpleIdentifierRequest::fromArray($request->post, 'code');
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        return $this->permitService->manualActivate($dto->identifier)
            ? "Zahlung für {$dto->identifier} bestätigt."
            : 'Fehler: Genehmigung nicht gefunden oder bereits bezahlt.';
    }
}
