<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\RequestMagicLink;

use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;

final readonly class HistoryRequestLinkRequest
{
    private function __construct(
        public string $email,
        public string $ip,
    ) {
    }

    public static function fromRequest(ServerRequest $request): self
    {
        $post = $request->post;
        $email = \trim((string) ($post['email'] ?? ''));
        if ($email === '' || !\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessage('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
        }
        $ip = $request->getIp();

        return new self($email, $ip);
    }
}
