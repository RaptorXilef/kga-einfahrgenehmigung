<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\PermitToggleSuspensionRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\PermitService;

/**
 * Action zum Sperren oder Entsperren einer aktiven Genehmigung.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PermitToggleSuspensionAction implements ActionInterface
{
    public function __construct(
        private PermitService $permitService,
    ) {
    }

    /**
     * TODO DOCBLOCK
     * Setzt den Sperrstatus (Suspension) einer Genehmigung.
     * Kontext: Interaktion mit PermitService::toggleSuspension().
     */
    public function execute(array $post): string
    {
        try {
            $dto = PermitToggleSuspensionRequest::fromArray($post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        if ($this->permitService->toggleSuspension($dto->code, $dto->isSuspended, $dto->reason)) {
            return 'Genehmigung wurde ' . ($dto->isSuspended ? 'gesperrt.' : 'freigegeben.');
        }

        return 'Fehler: Genehmigung nicht gefunden.';
    }
}
