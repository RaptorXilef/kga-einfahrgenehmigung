<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\AuthenticateAdmin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\System\AuditLoggerInterface;
use Override;

#[Route('GET', '/admin_login')]
#[Route('POST', '/admin_login')]
final readonly class AdminLoginAction implements ActionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
        private AuthenticateAdminHandler $loginHandler,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $redirectCode = (string) ($request->get['code'] ?? $request->post['code'] ?? '');

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
        $formData = $this->sessionManager->getFormData();
        $this->sessionManager->clearFormData();

        $html = $this->renderer->render('admin/login', [
            'formData' => $formData,
            'redirectCode' => $redirectCode,
        ]);

        return new HtmlResponse($html);
    }
}
