<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ConfirmPermitEmail;

use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;

final readonly class VerificationSubmitRequest
{
    private function __construct(
        public string $token,
        public string $ip,
    ) {
    }

    public static function fromRequest(ServerRequest $request): self
    {
        $token = isset($request->get['token'])
            ? (string) $request->get['token']
            : \trim((string) ($request->post['verification_code'] ?? ''));

        if ($token === '') {
            throw ValidationException::withMessage('Bitte geben Sie einen Verifizierungscode ein.');
        }
        $ip = $request->getIp();

        return new self($token, $ip);
    }
}
