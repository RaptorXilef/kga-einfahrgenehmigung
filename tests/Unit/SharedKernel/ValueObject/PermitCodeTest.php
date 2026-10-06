<?php
declare(strict_types=1);
namespace App\Tests\Unit\SharedKernel\ValueObject;

use App\SharedKernel\Domain\ValueObject\PermitCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PermitCode::class)]
final class PermitCodeTest extends TestCase
{
    #[Test]
    #[DataProvider('validCodeProvider')]
    public function it_accepts_and_normalizes_valid_permit_codes(string $input, string $expected): void
    {
        $code = new PermitCode($input);
        self::assertSame($expected, $code->value);
    }

    public static function validCodeProvider(): array
    {
        return [
            'standard code' => ['A1B2C3D4', 'A1B2C3D4'],
            'lowercase code' => ['a1b2c3d4', 'A1B2C3D4'],
            'with leading spaces' => ['  CODE123', 'CODE123'],
            'with dashes' => ['ML-0020-B-1234', 'ML-0020-B-1234'],
        ];
    }

    #[Test]
    #[DataProvider('invalidCodeProvider')]
    public function it_throws_exception_for_empty_permit_codes(string $invalidInput): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Der Permit-Code darf nicht leer sein.');

        new PermitCode($invalidInput);
    }

    public static function invalidCodeProvider(): array
    {
        return [
            'empty string' => [''],
            'spaces only' => ['   '],
        ];
    }
}
