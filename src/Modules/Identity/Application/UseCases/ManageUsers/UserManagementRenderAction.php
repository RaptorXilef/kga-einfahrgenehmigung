<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\View\TemplateRenderer;
use App\Modules\Identity\Application\Services\AuthService;
use App\Modules\Identity\Application\UseCases\GetUserManagementData\GetUserManagementDataHandler;
use App\Modules\Identity\Application\UseCases\GetUserManagementData\GetUserManagementDataQuery;

#[Route('GET', '/users')]
#[RequiresAuth]
final readonly class UserManagementRenderAction implements ViewActionInterface
{
    public function __construct(
        private AuthService $auth,
        private TemplateRenderer $renderer,
        private GetUserManagementDataHandler $dataHandler,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $viewDto = $this->dataHandler->handle(new GetUserManagementDataQuery());

        $html = $this->renderer->render('admin/users', [
            'auth' => $this->auth,
            'viewDto' => $viewDto,
        ]);

        return new HtmlResponse($html);
    }
}
