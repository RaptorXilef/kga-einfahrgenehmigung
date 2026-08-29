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
use App\Core\Entity\Role;
use App\Core\Security\Sanitizer;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\AuthService;

#[Route('POST', '/save_role')]
#[RequiresAuth]
final readonly class RoleSaveAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
        private RoleRepositoryInterface $roleRepository,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.roles.manage'; // Wir lassen den Key aus Kompatibilität vorerst so
    }

    public function execute(ServerRequest $request): mixed
    {
        $id = Sanitizer::string($request->post['group_id'] ?? '');
        $name = Sanitizer::string($request->post['group_name'] ?? '');
        $perms = (array) ($request->post['perms'] ?? []);

        if ($name === '') {
            $this->sessionManager->addFlash('error', 'Fehler: Der Rollenname darf nicht leer sein.');

            return new RedirectResponse('users.php');
        }

        $roles = $this->roleRepository->loadAll();
        $isUpdate = $id !== '' && isset($roles[$id]);

        if (!$isUpdate) {
            do {
                $id = $this->auth->generateId('role_');
            } while (isset($roles[$id]));
        }

        // Falls eine Basis-Rolle zur Vererbung ausgewählt wurde
        $inherit = Sanitizer::string($request->post['inherit_group'] ?? '');
        if (!$isUpdate && $inherit !== '' && isset($roles[$inherit])) {
            $perms = $roles[$inherit]->permissions;
        }

        $roles[$id] = new Role($id, $name, $perms);
        $this->roleRepository->saveAll($roles);

        if ($isUpdate) {
            if ($this->auth->getRole() === $id) {
                $this->auth->refreshSessionPermissions($id);
            }
            $this->auditLogger->log('ROLE_UPDATE', "Rechte-Matrix für Rolle '{$name}' (ID: {$id}) aktualisiert.");
            $this->sessionManager->addFlash('success', "Rechte für Rolle '{$name}' erfolgreich aktualisiert.");

            return new RedirectResponse('users.php');
        }

        $this->auditLogger->log('ROLE_CREATE', "Neue Rechte-Rolle '{$name}' (ID: {$id}) erstellt.");
        $this->sessionManager->addFlash('success', "Neue Rolle '{$name}' wurde erstellt.");

        return new RedirectResponse('users.php');
    }
}
