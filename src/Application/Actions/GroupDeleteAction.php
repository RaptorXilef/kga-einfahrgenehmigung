<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Core\Service\GroupService;

/**
 * Action zum Löschen einer Berechtigungsgruppe.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('delete_group')]
final readonly class GroupDeleteAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private GroupService $groupService,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.permissions.groups.manage';
    }

    /**
     * Löscht eine Gruppe aus dem Berechtigungssystem. Schützt die Kern-Gruppe 'admin'.
     *
     * @return string Ergebnisnachricht.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleIdentifierRequest::fromArray($request->post, 'group_id');
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
        }

        try {
            $this->groupService->deleteGroup($dto->identifier);
            $this->sessionManager->addFlash('success', 'Gruppe gelöscht.');

            return new RedirectResponse('users.php');
        } catch (\DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users.php');
        }
    }
}
