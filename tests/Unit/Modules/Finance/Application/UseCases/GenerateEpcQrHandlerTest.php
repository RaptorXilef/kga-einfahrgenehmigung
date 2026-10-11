<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Finance\Application\UseCases;

use App\Contracts\Config\ConfigInterface;
use App\Modules\Finance\Application\UseCases\GenerateEpcQr\GenerateEpcQrHandler;
use App\Modules\Finance\Application\UseCases\GenerateEpcQr\GenerateEpcQrQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GenerateEpcQrHandler::class)]
final class GenerateEpcQrHandlerTest extends TestCase
{
    #[Test]
    public function itGeneratesCorrectEpcQrString(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getString')->willReturnCallback(fn (string $key): string => match ($key) {
            'bic' => 'TESTBIC123',
            'kontoinhaber' => 'Max Mustermann',
            'iban' => 'DE123456789',
            default => ''
        });

        $handler = new GenerateEpcQrHandler($config);
        $result = $handler->handle(new GenerateEpcQrQuery(25.50, 'EFG-1234'));

        $this->assertStringContainsString('TESTBIC123', $result);
        $this->assertStringContainsString('Max Mustermann', $result);
        $this->assertStringContainsString('DE123456789', $result);
        $this->assertStringContainsString('EUR25.50', $result);
        $this->assertStringContainsString('EFG-1234', $result);
        $this->assertStringStartsWith("BCD\n001\n1\nSCT\n", $result);
    }
}
