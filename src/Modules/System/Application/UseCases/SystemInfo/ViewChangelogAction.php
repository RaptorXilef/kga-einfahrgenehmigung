<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\SystemInfo;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\View\TemplateRenderer;
use App\Contracts\System\SystemInfoInterface;
use App\Modules\Identity\Application\Services\AuthService;
use Override;

#[Route('GET', '/changelog')]
#[Route('POST', '/changelog')]
#[RequiresAuth]
final readonly class ViewChangelogAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuthService $auth,
        private TemplateRenderer $renderer,
        private SystemInfoInterface $sysInfo,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'system.update.execute';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $html = $this->renderer->render('admin/changelog', [
            'auth' => $this->auth,
            'markdownContent' => $this->sysInfo->getChangelog(),
        ]);

        return new HtmlResponse($html);
    }
}
