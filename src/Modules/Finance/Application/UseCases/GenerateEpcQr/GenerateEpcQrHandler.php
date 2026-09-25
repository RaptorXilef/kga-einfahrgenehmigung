<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\GenerateEpcQr;

use App\Contracts\Config\ConfigInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use Override;

/**
 * @implements QueryHandlerInterface<GenerateEpcQrQuery, string>
 */
final readonly class GenerateEpcQrHandler implements QueryHandlerInterface
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    /**
     * @param GenerateEpcQrQuery $query
     */
    #[Override]
    public function handle(mixed $query): string
    {
        return "BCD\n001\n1\nSCT\n" .
            $this->config->getString('bic') . "\n" .
            $this->config->getString('kontoinhaber') . "\n" .
            $this->config->getString('iban') . "\n" .
            'EUR' . \number_format($query->amount, 2, '.', '') . "\n" .
            "\n\n" . // Purpose Code & Structured Reference (leer)
            $query->reference;
    }
}
