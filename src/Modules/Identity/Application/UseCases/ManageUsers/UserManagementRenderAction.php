<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\View\TemplateRenderer;
use App\Contracts\Security\AuthorizationInterface;
use App\Modules\Identity\Application\UseCases\GetUserManagementData\GetUserManagementDataHandler;
use App\Modules\Identity\Application\UseCases\GetUserManagementData\GetUserManagementDataQuery;
use Override;

#[Route('GET', '/users')]
#[RequiresAuth]
final readonly class UserManagementRenderAction implements ViewActionInterface
{
    public function __construct(
        private AuthorizationInterface $auth,
        private TemplateRenderer $renderer,
        private GetUserManagementDataHandler $dataHandler,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $viewDto = $this->dataHandler->handle(new GetUserManagementDataQuery($this->auth));

        $html = $this->renderer->render('admin/users', [
            'auth' => $this->auth,
            'viewDto' => $viewDto,
        ]);

        return new HtmlResponse($html);
    }
}
