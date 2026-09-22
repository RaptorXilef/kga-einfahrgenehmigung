<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\AuthenticateAdmin;

use App\Application\Exception\ValidationException;

/**
 * DTO für den Login-Versuch eines Administrators.
 */
final readonly class AdminLoginRequest
{
    private function __construct(
        public string $password,
        public string $redirectCode,
        public string $username,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $user = \trim((string) ($post['user'] ?? ''));
        $pass = (string) ($post['pass'] ?? '');
        $code = \trim((string) ($post['code'] ?? ''));

        if ($user === '' || $pass === '') {
            throw ValidationException::withMessage('Bitte geben Sie einen Benutzernamen und ein Passwort ein.');
        }

        return new self($pass, $code, $user);
    }
}
