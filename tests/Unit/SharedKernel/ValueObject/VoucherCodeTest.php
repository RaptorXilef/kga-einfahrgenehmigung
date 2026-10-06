<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\ValueObject;

use App\SharedKernel\Domain\ValueObject\VoucherCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(VoucherCode::class)]
final class VoucherCodeTest extends TestCase
{
    #[Test]
    #[DataProvider('validVoucherProvider')]
    public function itAcceptsAndNormalizesVoucherCodes(string $input, string $expected): void
    {
        $code = new VoucherCode($input);

        $this->assertSame($expected, $code->value);
        $this->assertSame($expected, (string) $code);
    }

    public static function validVoucherProvider(): array
    {
        return [
            'standard code' => ['V-1234-ABCD', 'V-1234-ABCD'],
            'lowercase' => ['sommer26', 'SOMMER26'],
            'with spaces' => ['  WINTER-2026  ', 'WINTER-2026'],
        ];
    }

    #[Test]
    public function itThrowsExceptionForEmptyVoucherCodes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Der Gutscheincode darf nicht leer sein.');

        new VoucherCode('   ');
    }
}
