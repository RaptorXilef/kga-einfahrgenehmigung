<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\ValueObject;

use App\SharedKernel\Domain\ValueObject\TemplateKey;
use InvalidArgumentException;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TemplateKey::class)]
final class TemplateKeyTest extends TestCase
{
    #[Test]
    #[DataProvider('validKeyProvider')]
    public function itAcceptsAndNormalizesValidTemplateKeys(string $input, string $expected): void
    {
        $key = new TemplateKey($input);
        $this->assertSame($expected, $key->value);
    }

    public static function validKeyProvider(): Iterator
    {
        yield 'standard key' => ['std_7', 'std_7'];
        yield 'uppercase to lower' => ['PERM_12', 'perm_12'];
        yield 'legacy dot format' => ['std.14', 'std_14'];
        yield 'with spaces' => ['  custom_perm  ', 'custom_perm'];
    }

    #[Test]
    #[DataProvider('invalidKeyProvider')]
    public function itThrowsExceptionForEmptyOrInvalidTemplateKeys(string $invalidInput, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        // KILLT MUTANTE 12: Fehler genau validieren
        $this->expectExceptionMessage($expectedMessage);
        new TemplateKey($invalidInput);
    }

    public static function invalidKeyProvider(): Iterator
    {
        yield 'empty string' => ['', 'Der Template-Key darf nicht leer sein.'];
        yield 'spaces only' => ['   ', 'Der Template-Key darf nicht leer sein.'];
        yield 'special chars' => ['std_7!', 'Ungültiges Format für Template-Key: std_7!'];
        yield 'invalid formatting' => ['my/template', 'Ungültiges Format für Template-Key: my/template'];
    }
}
