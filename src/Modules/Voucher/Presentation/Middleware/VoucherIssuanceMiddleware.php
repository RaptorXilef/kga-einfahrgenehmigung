<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Presentation\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\Identity\Application\Services\AuthService;
use Override;

/**
 * Guard für die Erstellung von Gutscheinen (Template-Berechtigung).
 */
final readonly class VoucherIssuanceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private SessionManager $sessionManager,
    ) {
    }

    #[Override]
    public function process(ServerRequest $request, callable $next): ResponseInterface
    {
        if (!$this->auth->hasPermission('vouchers.create')) {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Berechtigung, Gutscheine zu erstellen.');

            return new RedirectResponse('admin');
        }

        $templateKey = (string) ($request->post['template_key'] ?? 'std_7');

        if (!$this->auth->hasPermission("template.{$templateKey}")) {
            $this->sessionManager->addFlash('error', "Fehler: Sie haben keine Berechtigung für Typ '{$templateKey}'.");

            return new RedirectResponse('admin');
        }

        return $next($request);
    }
}
