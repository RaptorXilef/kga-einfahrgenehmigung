<?php

declare(strict_types=1);

namespace App\Application\Response;

use App\Application\Contracts\ResponseInterface;
use Override;

/**
 * Repräsentiert eine einfache Klartext-HTTP-Antwort (text/plain).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class TextResponse implements ResponseInterface
{
    public function __construct(public string $content, public int $status = 200)
    {
    }

    #[Override]
    public function send(): void
    {
        \http_response_code($this->status);
        \header('Content-Type: text/plain; charset=utf-8');
        echo $this->content;
        exit;
    }
}
