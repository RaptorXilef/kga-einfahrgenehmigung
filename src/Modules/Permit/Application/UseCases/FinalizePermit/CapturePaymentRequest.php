<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\FinalizePermit;

use App\Application\Exception\ValidationException;

final readonly class CapturePaymentRequest
{
    private function __construct(
        public string $orderId,
        public string $token,
    ) {
    }

    public static function fromArray(array $input): self
    {
        $orderId = (string) ($input['orderID'] ?? '');
        $token = (string) ($input['token'] ?? '');

        if ($orderId === '' || $token === '') {
            throw ValidationException::withMessage('Fehlende Parameter (orderID oder token).');
        }

        return new self($orderId, $token);
    }
}
