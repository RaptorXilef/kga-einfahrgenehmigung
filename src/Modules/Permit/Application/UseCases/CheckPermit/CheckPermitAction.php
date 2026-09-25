<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\CheckPermit;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthorizationInterface;
use Override;

#[Route('GET', '/check')]
final readonly class CheckPermitAction implements ViewActionInterface
{
    public function __construct(
        private AuthorizationInterface $auth,
        private ConfigInterface $config,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
        private GetPermitCheckDetailsHandler $checkHandler,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = CheckPermitRequest::fromArray($request->get);
        } catch (ValidationException) {
            $html = $this->renderer->render('frontend/check_search');

            return new HtmlResponse($html);
        }

        $query = new GetPermitCheckDetailsQuery($dto->code, $this->auth->isLoggedIn(), $dto->token);
        $details = $this->checkHandler->handle($query);

        if (!$details->isFound) {
            $this->sessionManager->addFlash('error', "Code '{$dto->code}' nicht gefunden.");
            $html = $this->renderer->render('frontend/check_search');

            return new HtmlResponse($html);
        }

        $adminData = [
            'adminUser' => $this->auth->getUsername(),
            'adminId' => $this->auth->getUserId(),
            'adminGroup' => $this->auth->getRole(),
        ];

        $template = $details->showAdminView ? 'frontend/check_admin' : 'frontend/check_public';

        $html = $this->renderer->render($template, \array_merge($adminData, [
            'auth' => $this->auth,
            'details' => $details,
        ]));

        return new HtmlResponse($html);
    }
}
