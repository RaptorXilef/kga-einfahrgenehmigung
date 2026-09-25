<?php

declare(strict_types=1);

namespace App\Application\Response;

use App\Application\Contracts\ResponseInterface;
use Override;

/**
 * Repräsentiert eine leere HTTP-Antwort (z.B. 204 No Content oder Status-Codes ohne Body).
 */
final readonly class EmptyResponse implements ResponseInterface
{
    public function __construct(public int $status = 204)
    {
    }

    #[Override]
    public function send(): void
    {
        \http_response_code($this->status);
        exit;
    }
}
