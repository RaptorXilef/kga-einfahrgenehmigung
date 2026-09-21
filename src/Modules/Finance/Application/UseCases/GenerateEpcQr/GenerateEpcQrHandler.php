<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\GenerateEpcQr;

use App\Contracts\Config\ConfigInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;

/**
 * @implements QueryHandlerInterface<GenerateEpcQrQuery, string>
 */
final readonly class GenerateEpcQrHandler implements QueryHandlerInterface
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    public function handle(mixed $query): string
    {
        return "BCD\n001\n1\nSCT\n" .
            $this->config->get('bic') . "\n" .
            $this->config->get('kontoinhaber') . "\n" .
            $this->config->get('iban') . "\n" .
            'EUR' . \number_format($query->amount, 2, '.', '') . "\n" .
            "\n\n" . // Purpose Code & Structured Reference (leer)
            $query->reference;
    }
}
