<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\ValueObject;

use App\SharedKernel\Domain\ValueObject\PermitCode;
use InvalidArgumentException;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PermitCode::class)]
final class PermitCodeTest extends TestCase
{
    #[Test]
    #[DataProvider('validCodeProvider')]
    public function itAcceptsAndNormalizesValidPermitCodes(string $input, string $expected): void
    {
        $code = new PermitCode($input);
        $this->assertSame($expected, $code->value);
    }

    public static function validCodeProvider(): Iterator
    {
        yield 'standard code' => ['A1B2C3D4', 'A1B2C3D4'];
        yield 'lowercase code' => ['a1b2c3d4', 'A1B2C3D4'];
        yield 'with leading spaces' => ['  CODE123', 'CODE123'];
        yield 'with dashes' => ['ML-0020-B-1234', 'ML-0020-B-1234'];
    }

    #[Test]
    #[DataProvider('invalidCodeProvider')]
    public function itThrowsExceptionForEmptyPermitCodes(string $invalidInput): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Der Permit-Code darf nicht leer sein.');

        new PermitCode($invalidInput);
    }

    public static function invalidCodeProvider(): Iterator
    {
        yield 'empty string' => [''];
        yield 'spaces only' => ['   '];
    }
}
