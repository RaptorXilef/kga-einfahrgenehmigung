<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\AuthenticateAdmin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\View\TemplateRenderer;
use App\Modules\Identity\Application\Services\AuthService;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\System\Application\Services\AuditLoggerService;

#[Route('GET', '/admin_login')]
#[Route('POST', '/admin_login')]
final readonly class AdminLoginAction implements ActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
        private RoleRepositoryInterface $roleRepository,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
        private AuthenticateAdminHandler $loginHandler,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $redirectCode = (string) ($request->get['code'] ?? $request->post['code'] ?? '');

        // VSA FIX: Die Middleware kümmert sich um POST Fehler und leitet auf GET zurück!
        if ($request->getMethod() === 'GET') {
            return $this->renderForm($redirectCode);
        }

        $dto = AdminLoginRequest::fromArray($request->post);

        $command = new AuthenticateAdminCommand($dto->username, $dto->password, $request->getIp());
        $this->loginHandler->handle($command);

        $this->auditLogger->log('LOGIN', 'Erfolgreicher Login in den Adminbereich.');

        if ($dto->redirectCode !== '') {
            return new RedirectResponse('check?code=' . \urlencode($dto->redirectCode));
        }

        return new RedirectResponse('admin');
    }

    private function renderForm(string $redirectCode): HtmlResponse
    {
        $html = $this->renderer->render('admin/login', [
            'auth' => $this->auth,
            'roleRepository' => $this->roleRepository,
            'userRepository' => $this->userRepository,
            'redirectCode' => $redirectCode,
        ]);

        return new HtmlResponse($html);
    }
}
