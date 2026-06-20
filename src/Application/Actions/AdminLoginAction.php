<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\AdminLoginRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * Action für den Login von Administratoren inkl. Rate-Limiting und CSRF-Schutz.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class AdminLoginAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private GroupRepositoryInterface $groupRepository,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = AdminLoginRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->renderForm($e->getMessage());

            return null;
        }

        try {
            if ($this->auth->login($dto->username, $dto->password, $request->getIp())) {
                if ($dto->redirectCode !== '') {
                    return new RedirectResponse('check.php?code=' . \urlencode($dto->redirectCode));
                }

                return new RedirectResponse('admin.php');
            }
            $this->renderForm('Benutzername oder Passwort ist falsch.');

            return null;
        } catch (\RuntimeException $e) {
            $this->renderForm($e->getMessage());

            return null;
        }
    }

    /**
     * Hilfsmethode zum erneuten Rendern des Login-Formulars bei Fehlern.
     */
    private function renderForm(string $message): void
    {
        $this->renderer->render('admin_login', [
            'auth'            => $this->auth,
            'groupRepository' => $this->groupRepository,
            'message'         => $message,
            'userRepository'  => $this->userRepository,
        ]);
    }
}
