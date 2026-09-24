<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\System;

use App\Contracts\System\IpResolverInterface;

/**
 * Physische Implementierung der IP-Auflösung via Superglobal $_SERVER.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class ServerIpResolver implements IpResolverInterface
{
    public function getIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($keys as $k) {
            if (isset($_SERVER[$k]) && \is_string($_SERVER[$k]) && $_SERVER[$k] !== '') {
                $ips = \explode(',', $_SERVER[$k]);

                return \trim($ips[0]);
            }
        }

        return '0.0.0.0';
    }
}
