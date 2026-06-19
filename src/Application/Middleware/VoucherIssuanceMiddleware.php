<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Response\RedirectResponse;
use App\Contracts\Application\MiddlewareInterface;
use App\Core\Service\AuthService;

/**
 * Guard für die Erstellung von Gutscheinen (Template-Berechtigung).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VoucherIssuanceMiddleware implements MiddlewareInterface
{
    public function __construct(private AuthService $auth)
    {
    }

    public function process(array $requestData, callable $next): mixed
    {
        if (! $this->auth->hasPermission('dashboard.generator-tools.voucher_gen.execute')) {
            return new RedirectResponse('admin.php?msg=' . \urlencode('Fehler: Keine Berechtigung, Gutscheine zu erstellen.'));
        }

        $templateKey = (string) ($requestData['post']['template_key'] ?? 'std_7');

        if (! $this->auth->hasPermission("template.{$templateKey}")) {
            return new RedirectResponse('admin.php?msg=' . \urlencode("Fehler: Sie haben keine Berechtigung für Typ '{$templateKey}'."));
        }

        return $next($requestData);
    }
}
