<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\DeleteVoucher;

use App\Application\Exception\ValidationException;

final readonly class DeleteVoucherRequest
{
    private function __construct(
        public string $code,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $code = \trim((string) ($post['code'] ?? ''));

        if ($code === '' || !\preg_match('/^[a-zA-Z0-9_\-]+$/', $code)) {
            throw ValidationException::withMessage('Fehler: Ungültiger oder fehlender Gutscheincode.');
        }

        return new self($code);
    }
}
