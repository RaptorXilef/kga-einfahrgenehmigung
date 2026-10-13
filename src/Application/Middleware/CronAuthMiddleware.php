<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\EmptyResponse;
use App\Contracts\Config\ConfigInterface;
use Override;

final readonly class CronAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        $provided = $request->get['token'] ?? '';
        $req = $this->config->getString('cron_secret');

        // CLI-Erkennung über den ServerRequest statt nativer Funktion (php_sapi_name).
        // Das sorgt für 100% Testbarkeit der Schicht, ohne die Umgebungsebene manipulieren zu müssen.
        // Ein Web-Server injiziert kein 'argc' in das $_SERVER Array.
        $isCli = isset($request->server['argc']);

        if (!$isCli && $provided !== $req) {
            return new EmptyResponse(403);
        }

        return $next($request);
    }
}
