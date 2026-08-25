<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Core\Security\Sanitizer;
use App\Core\Service\AuditLoggerService;

#[Route('POST', '/delete_role')]
#[RequiresAuth]
final readonly class RoleDeleteAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private RoleRepositoryInterface $roleRepository,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.permissions.groups.manage';
    }

    public function execute(ServerRequest $request): mixed
    {
        $id = Sanitizer::string($request->post['group_id'] ?? '');

        if ($id === '') {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Rollen-ID übermittelt.');

            return new RedirectResponse('users.php');
        }

        if ($id === 'admin') {
            $this->sessionManager->addFlash('error', 'Fehler: Die Admin-Rolle kann nicht gelöscht werden.');

            return new RedirectResponse('users.php');
        }

        $roles = $this->roleRepository->loadAll();
        if (!isset($roles[$id])) {
            $this->sessionManager->addFlash('error', 'Fehler: Rolle nicht gefunden.');

            return new RedirectResponse('users.php');
        }

        $roleName = $roles[$id]->name;
        unset($roles[$id]);
        $this->roleRepository->saveAll($roles);

        $this->auditLogger->log('ROLE_DELETE', "Rechte-Rolle '{$roleName}' (ID: {$id}) wurde gelöscht.");
        $this->sessionManager->addFlash('success', 'Rolle gelöscht. (Zugeordnete Benutzer fallen auf Standard-Rechte zurück).');

        return new RedirectResponse('users.php');
    }
}
