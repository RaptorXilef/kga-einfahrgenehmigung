<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageProfile;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Security\AuthorizationInterface;
use Override;

#[Route('GET', '/profile')]
#[RequiresAuth]
final readonly class ProfileRenderAction implements ViewActionInterface
{
    public function __construct(
        private AuthorizationInterface $auth,
        private GetProfileDataHandler $profileDataHandler,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $userId = $this->auth->getUserId();

        if (\str_starts_with($userId, 'sys_')) {
            $this->sessionManager->addFlash('info', 'System-Accounts können nicht über das Frontend bearbeitet werden.');

            return new RedirectResponse('admin');
        }

        $viewDto = $this->profileDataHandler->handle(new GetProfileDataQuery($userId));

        $html = $this->renderer->render('admin/profile', [
            'viewDto' => $viewDto,
            'role' => $viewDto->roleName,
            'userId' => $viewDto->userId,
            'username' => $viewDto->username,
            'userImage' => $viewDto->userImage,
        ]);

        return new HtmlResponse($html);
    }
}
