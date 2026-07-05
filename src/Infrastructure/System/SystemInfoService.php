<?php

declare(strict_types=1);

namespace App\Infrastructure\System;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Contracts\System\SystemInfoInterface;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemInfoService implements SystemInfoInterface
{
    public function __construct(
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    public function getChangelog(): string
    {
        $path = \rtrim((string) $this->config->get('root_path'), '/\\') . '/CHANGELOG.md';
        if (! \file_exists($path)) {
            $path = \str_replace('.md', '.MD', $path);
        }

        return \file_exists($path) ? \file_get_contents($path) : 'Kein Changelog gefunden.';
    }

    public function getCurrentVersion(): string
    {
        $path = \rtrim((string) $this->config->get('root_path'), '/\\') . '/package.json';
        if (\file_exists($path)) {
            try {
                $data = $this->jsonHelper->read($path);
                if (isset($data['version'])) {
                    return 'v' . $data['version'];
                }
            } catch (\Exception $e) {
            }
        }

        return 'v0.0.0';
    }
}
