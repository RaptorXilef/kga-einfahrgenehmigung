<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Contracts\ActionInterface;
use App\Application\DTO\AdminLoginRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\AuthService;

/**
 * Action für den Login von Administratoren inkl. Rate-Limiting und CSRF-Schutz.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('admin_login')]
final readonly class AdminLoginAction implements ActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
        private GroupRepositoryInterface $groupRepository,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = AdminLoginRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->rescueFormData($request); // UX: Daten retten
            $this->renderForm($e->getMessage());

            return null;
        }

        try {
            if ($this->auth->login($dto->username, $dto->password, $request->getIp())) {

                // LOG SCHREIBEN
                $this->auditLogger->log('LOGIN', 'Erfolgreicher Login in den Adminbereich.');

                if ($dto->redirectCode !== '') {
                    return new RedirectResponse('check.php?code=' . \urlencode($dto->redirectCode));
                }

                return new RedirectResponse('admin.php');
            }

            $this->rescueFormData($request); // UX: Daten retten, wenn Passwort falsch war
            $this->renderForm('Benutzername oder Passwort ist falsch.');

            return null;
        } catch (\RuntimeException $e) {
            $this->rescueFormData($request); // UX: Daten retten bei Rate-Limit-Fehlern
            $this->renderForm($e->getMessage());

            return null;
        }
    }

    /**
     * Sichert die Formulardaten in der direkten PHP-Session für die Ausgabe im Formular.
     */
    private function rescueFormData(ServerRequest $request): void
    {
        // Wir nutzen hier direkt $_SESSION, da das Admin-Login-Formular diese
        // Variable so erwartet, wie in der vorherigen Template-Anpassung definiert.
        $postData = $request->post;
        unset($postData['csrf_token'], $postData['action'], $postData['code']);
        $_SESSION['form_data'] = $postData;
    }

    /**
     * Hilfsmethode zum erneuten Rendern des Login-Formulars bei Fehlern.
     */
    private function renderForm(string $message): void
    {
        if ($message !== '') {
            $this->sessionManager->addFlash('error', $message);
        }

        $this->renderer->render('admin_login', [
            'auth'            => $this->auth,
            'groupRepository' => $this->groupRepository,
            'userRepository'  => $this->userRepository,
        ]);
    }
}
