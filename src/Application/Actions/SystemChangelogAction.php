<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Http\ServerRequest;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\AuthService;
use App\Infrastructure\System\SystemInfoService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('changelog')]
final readonly class SystemChangelogAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuthService $auth,
        private TemplateRenderer $renderer,
        private SystemInfoService $sysInfo,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.update.view';
    }

    public function execute(ServerRequest $request): mixed
    {
        $this->renderer->render('changelog', [
            'auth'            => $this->auth,
            'markdownContent' => $this->sysInfo->getChangelog(),
        ]);

        return null;
    }
}
