<?php

declare(strict_types=1);

namespace App\Application\Actions\Frontend;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\DTO\AdminLoginRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface; // Nur noch für das View-Rendering gebraucht
use App\Core\Service\AuditLoggerService;
use App\Core\Service\AuthService; // Nur noch für das View-Rendering gebraucht
use App\Modules\Identity\Application\UseCases\AuthenticateAdmin\AuthenticateAdminCommand;
use App\Modules\Identity\Application\UseCases\AuthenticateAdmin\AuthenticateAdminHandler;
use DomainException;
use RuntimeException;

#[Route('GET', '/admin_login')]
#[Route('POST', '/admin_login')]
final readonly class AdminLoginAction implements ActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
        private RoleRepositoryInterface $roleRepository,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
        private AuthenticateAdminHandler $loginHandler, // <-- CQRS Injected
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        // Sauberer GET-Handler: Rendert einfach das Formular
        if ($request->getMethod() === 'GET') {
            return $this->renderForm('');
        }

        // Ab hier: Verarbeitung des POST-Logins
        try {
            $dto = AdminLoginRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->rescueFormData($request);

            return $this->renderForm($e->getMessage());
        }

        try {
            // CQRS Command triggern
            $command = new AuthenticateAdminCommand(
                $dto->username,
                $dto->password,
                $request->getIp(),
            );

            $this->loginHandler->handle($command);

            // Wenn handle() keine Exception wirft, war der Login erfolgreich!
            $this->auditLogger->log('LOGIN', 'Erfolgreicher Login in den Adminbereich.');

            if ($dto->redirectCode !== '') {
                return new RedirectResponse('check?code=' . \urlencode($dto->redirectCode));
            }

            return new RedirectResponse('admin');

        } catch (DomainException|RuntimeException $e) {
            // Fängt "Falsches Passwort", "Gesperrt" oder "Zu viele Versuche" ab
            $this->rescueFormData($request);

            return $this->renderForm($e->getMessage());
        }
    }

    private function rescueFormData(ServerRequest $request): void
    {
        $postData = $request->post;
        unset($postData['csrf_token'], $postData['action'], $postData['code']);
        $_SESSION['form_data'] = $postData;
    }

    private function renderForm(string $message): HtmlResponse
    {
        if ($message !== '') {
            $this->sessionManager->addFlash('error', $message);
        }

        $html = $this->renderer->render('admin/login', [
            'auth' => $this->auth,
            'roleRepository' => $this->roleRepository,
            'userRepository' => $this->userRepository,
        ]);

        return new HtmlResponse($html);
    }
}
