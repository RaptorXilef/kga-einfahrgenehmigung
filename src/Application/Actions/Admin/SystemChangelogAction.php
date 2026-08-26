<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\View\TemplateRenderer;
use App\Contracts\System\SystemInfoInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('GET', '/changelog')]
#[Route('POST', '/changelog')]
final readonly class SystemChangelogAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuthService $auth,
        private TemplateRenderer $renderer,
        private SystemInfoInterface $sysInfo,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.update.view';
    }

    public function execute(ServerRequest $request): mixed
    {
        $this->renderer->render('admin/changelog', [
            'auth' => $this->auth,
            'markdownContent' => $this->sysInfo->getChangelog(),
        ]);

        return null;
    }
}
