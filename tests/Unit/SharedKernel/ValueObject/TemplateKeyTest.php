<?php
declare(strict_types=1);
namespace App\Tests\Unit\SharedKernel\ValueObject;

use App\SharedKernel\Domain\ValueObject\TemplateKey;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TemplateKey::class)]
final class TemplateKeyTest extends TestCase
{
    #[Test]
    #[DataProvider('validKeyProvider')]
    public function it_accepts_and_normalizes_valid_template_keys(string $input, string $expected): void
    {
        $key = new TemplateKey($input);
        self::assertSame($expected, $key->value);
    }

    public static function validKeyProvider(): array
    {
        return [
            'standard key' => ['std_7', 'std_7'],
            'uppercase to lower' => ['PERM_12', 'perm_12'],
            'legacy dot format' => ['std.14', 'std_14'],
            'with spaces' => ['  custom_perm  ', 'custom_perm'],
        ];
    }

    #[Test]
    #[DataProvider('invalidKeyProvider')]
    public function it_throws_exception_for_empty_or_invalid_template_keys(string $invalidInput): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TemplateKey($invalidInput);
    }

    public static function invalidKeyProvider(): array
    {
        return [
            'empty string' => [''],
            'spaces only' => ['   '],
            'special chars' => ['std_7!'],
            'invalid formatting' => ['my/template'],
        ];
    }
}
