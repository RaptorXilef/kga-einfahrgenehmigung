<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SubmitPermitRequest;

final readonly class ViewRenderRequest
{
    private function __construct(
        public bool $isSuccess,
        public int $step,
        public int $loadArchive,
    ) {
    }

    public static function fromArray(array $get): self
    {
        return new self(
            isset($get['sent']),
            ($get['sent'] ?? '0') === '1' ? 2 : 1,
            (int) ($get['load_archive'] ?? 0),
        );
    }
}
