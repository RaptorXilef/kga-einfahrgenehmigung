<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\MiddlewareInterface;
use App\Core\Service\AuthService;

/**
 * Guard für die Erstellung von Gutscheinen (Template-Berechtigung).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VoucherIssuanceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $auth,
        private SessionManager $sessionManager,
    ) {
    }

    public function process(ServerRequest $request, callable $next): mixed
    {
        if (! $this->auth->hasPermission('dashboard.generator-tools.voucher_gen.execute')) {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Berechtigung, Gutscheine zu erstellen.');

            return new RedirectResponse('admin.php');
        }

        $templateKey = (string) ($request->post['template_key'] ?? 'std_7');

        if (! $this->auth->hasPermission("template.{$templateKey}")) {
            $this->sessionManager->addFlash('error', "Fehler: Sie haben keine Berechtigung für Typ '{$templateKey}'.");

            return new RedirectResponse('admin.php');
        }

        return $next($request);
    }
}
