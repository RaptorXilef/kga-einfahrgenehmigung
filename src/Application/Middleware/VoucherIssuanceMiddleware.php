<?php

declare(strict_types=1);

namespace App\Application\Middleware;

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
            \header('Location: admin.php?msg=' . \urlencode('Fehler: Keine Berechtigung, Gutscheine zu erstellen.'));
            exit;
        }

        $templateKey = (string) ($requestData['post']['template_key'] ?? 'std_7');

        if (! $this->auth->hasPermission("template.{$templateKey}")) {
            \header('Location: admin.php?msg=' . \urlencode("Fehler: Sie haben keine Berechtigung, den Typ '{$templateKey}' zu verwenden."));
            exit;
        }

        return $next($requestData);
    }
}
