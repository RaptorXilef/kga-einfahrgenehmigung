<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;
use App\Core\Service\PermitService;

/**
 * Action zum manuellen Markieren einer Genehmigung als 'bezahlt'.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PermitMarkAsPaidAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private PermitService $permitService,
    ) {
    }

    /**
     * Markiert eine Genehmigung manuell als bezahlt im Storage.
     *
     * Nutzt PermitService::manualActivate().
     *
     * @param array<string, mixed> $post
     *
     * @return string Erfolgsmeldung oder leerer String bei Fehler.
     */
    public function execute(array $post): mixed
    {
        if (! $this->auth->hasPermission('dashboard.finance.mark_paid')) {
            return 'Fehler: Keine Berechtigung für diese Aktion.';
        }

        try {
            // Hinweis: Das Formular übergibt 'code', nicht 'id'
            $dto = SimpleIdentifierRequest::fromArray($post, 'code');
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        return $this->permitService->manualActivate($dto->identifier)
            ? "Zahlung für {$dto->identifier} bestätigt."
            : 'Fehler: Genehmigung nicht gefunden oder bereits bezahlt.';
    }
}
