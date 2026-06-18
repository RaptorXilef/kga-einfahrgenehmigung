<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Core\Service\AuthService;
use App\Core\Service\PermitService;

/**
 * Action zum Sperren oder Entsperren einer aktiven Genehmigung.
 *
 * Path: src/Application/Actions/PermitToggleSuspensionAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PermitToggleSuspensionAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private PermitService $permitService,
        private StorageInterface $storage,
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
            $dto = SimpleIdentifierRequest::fromArray($post, 'code');
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        $permit = $this->storage->findByHash($dto->identifier);

        if (! $permit instanceof Permit) {
            return 'Fehler: Genehmigung nicht gefunden.';
        }

        $isUnpaid = \strtolower(\trim($permit->getStatus())) !== 'bezahlt';

        $hasRight = false;
        if ($isUnpaid && $this->auth->hasPermission('dashboard.finance.suspend')) {
            $hasRight = true;
        } elseif (! $isUnpaid && $this->auth->hasPermission('dashboard.active.suspend')) {
            $hasRight = true;
        }

        if (! $hasRight) {
            return 'Fehler: Keine Berechtigung, diesen spezifischen Status zu sperren/entsperren.';
        }

        $suspended = ($post['action'] ?? '') === 'suspend_permit';
        $reason    = (string) ($post['reason'] ?? '');

        $this->permitService->toggleSuspension($dto->identifier, $suspended, $reason);

        return 'Genehmigung wurde ' . ($suspended ? 'gesperrt.' : 'freigegeben.');
    }
}
