<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\SystemInfo;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\View\TemplateRenderer;
use App\Contracts\System\SystemInfoInterface;
use App\Modules\Identity\Application\Services\AuthService;

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

    public function getRequiredPermission(): string
    {
        return 'system.update.execute';
    }

    public function execute(ServerRequest $request): mixed
    {
        $html = $this->renderer->render('admin/changelog', [
            'auth' => $this->auth,
            'markdownContent' => $this->sysInfo->getChangelog(),
        ]);

        return new HtmlResponse($html);
    }
}
