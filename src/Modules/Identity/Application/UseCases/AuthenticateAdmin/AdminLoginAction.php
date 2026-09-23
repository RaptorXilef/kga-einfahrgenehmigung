<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\AuthenticateAdmin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Modules\Identity\Application\Services\AuthService;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\System\Application\Services\AuditLoggerService;
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
        private AuthenticateAdminHandler $loginHandler,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        if ($request->getMethod() === 'GET') {
            return $this->renderForm('');
        }

        try {
            $dto = AdminLoginRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->rescueFormData($request);

            return $this->renderForm($e->getMessage());
        }

        try {
            $command = new AuthenticateAdminCommand($dto->username, $dto->password, $request->getIp());
            $this->loginHandler->handle($command);

            $this->auditLogger->log('LOGIN', 'Erfolgreicher Login in den Adminbereich.');

            if ($dto->redirectCode !== '') {
                return new RedirectResponse('check?code=' . \urlencode($dto->redirectCode));
            }

            return new RedirectResponse('admin');
        } catch (DomainException|RuntimeException $e) {
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
