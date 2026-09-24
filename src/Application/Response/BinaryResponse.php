<?php

declare(strict_types=1);

namespace App\Application\Response;

use App\Application\Contracts\ResponseInterface;
use Override;

/**
 * Streamt beliebige Binärdaten (z.B. PNG-Bilder für QR-Codes) mit konfigurierbaren Headern.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class BinaryResponse implements ResponseInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $content,
        public string $contentType,
        public int $statusCode = 200,
        public array $headers = [],
    ) {
    }

    #[Override]
    public function send(): void
    {
        \http_response_code($this->statusCode);
        if (!\headers_sent()) {
            \header('Content-Type: ' . $this->contentType);
            foreach ($this->headers as $name => $value) {
                \header($name . ': ' . $value);
            }
        }

        echo $this->content;
        exit;
    }
}
