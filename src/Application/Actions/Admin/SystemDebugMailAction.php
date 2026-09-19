<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\TextResponse;
use App\Contracts\Config\ConfigInterface;

/**
 * Rendered eine lokal abgefangene (gespoolte) HTML-E-Mail im Browser.
 * Ausschließlich im Debug-Modus verfügbar.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('GET', '/debug_mail')]
#[RequiresAuth]
final readonly class SystemDebugMailAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'system.logs.view';
    }

    public function execute(ServerRequest $request): mixed
    {
        if ($this->config->get('debug_mode', false) !== true) {
            return new TextResponse('Der Debug-Modus ist nicht aktiv.', 403);
        }

        $file = $request->get['file'] ?? '';

        // Striktes Path-Traversal Prevention! Erlaubt nur Datums-ID Formate.
        if ($file === '' || !\preg_match('/^[a-zA-Z0-9_]+\.html$/', $file)) {
            return new TextResponse('Ungueltiger oder fehlender Dateiname.', 400);
        }

        $path = \rtrim((string) $this->config->get('root_path', ''), '/\\') . '/storage/debug_mails/' . $file;

        if (!\file_exists($path)) {
            return new TextResponse('E-Mail-Spool-Datei nicht gefunden. Eventuell wurde sie geloescht.', 404);
        }

        return new HtmlResponse(\file_get_contents($path));
    }
}
