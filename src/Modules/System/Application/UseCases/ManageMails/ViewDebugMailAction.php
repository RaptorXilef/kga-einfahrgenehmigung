<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageMails;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\TextResponse;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailLogInterface;
use Override;

#[Route('GET', '/debug_mail')]
#[RequiresAuth]
final readonly class ViewDebugMailAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private MailLogInterface $mailLog,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'system.logs.view';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        if (!$this->config->getBool('debug_mode', false)) {
            return new TextResponse('Der Debug-Modus ist nicht aktiv.', 403);
        }

        $file = $request->get['file'] ?? '';

        if ($file === '') {
            return new TextResponse('Ungueltiger oder fehlender Dateiname.', 400);
        }

        $content = $this->mailLog->getDebugMailContent($file);

        if ($content === null) {
            return new TextResponse('E-Mail-Spool-Datei nicht gefunden oder ungueltig. Eventuell wurde sie geloescht.', 404);
        }

        return new HtmlResponse($content);
    }
}
