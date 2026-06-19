<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Response\RedirectResponse;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Core\Service\AuthService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemChangelogAction implements ViewActionInterface
{
    public function __construct(
        private AuthService $auth,
        private ConfigInterface $config,
        private TemplateRenderer $renderer,
    ) {
    }

    public function execute(array $requestData): mixed
    {
        if (! $this->auth->isLoggedIn() || ! $this->auth->hasPermission('system.update.view')) {
            return new RedirectResponse('index.php');
        }
        $root          = \rtrim((string) $this->config->get('root_path'), '/\\');
        $changelogPath = $root . '/CHANGELOG.md';
        if (! \file_exists($changelogPath)) {
            $changelogPath = $root . '/CHANGELOG.MD';
        }
        $markdownContent = \file_exists($changelogPath)
            ? \file_get_contents($changelogPath)
            : 'Kein Changelog gefunden.';
        $this->renderer->render('changelog', [
            'auth'            => $this->auth,
            'markdownContent' => $markdownContent,
        ]);

        return null;
    }
}
