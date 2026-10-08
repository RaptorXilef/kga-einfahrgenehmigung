<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Application\Security;

use App\SharedKernel\Application\Security\Sanitizer;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stringable;

#[CoversClass(Sanitizer::class)]
final class SanitizerTest extends TestCase
{
    #[Test]
    #[DataProvider('stringInputProvider')]
    public function itSanitizesStringsAndStripsTags(mixed $input, string $expected): void
    {
        $this->assertSame($expected, Sanitizer::string($input));
    }

    public static function stringInputProvider(): Iterator
    {
        yield 'html tags and spaces' => ['  <b>Max</b> <script>alert(1)</script>Mustermann  ', 'Max alert(1)Mustermann'];
        yield 'integer scalar' => [42, '42'];
        yield 'float scalar' => [19.99, '19.99'];
        yield 'stringable object' => [
            new class implements Stringable {
                public function __toString(): string
                {
                    return '  <i>Aus Objekt</i>  ';
                }
            },
            'Aus Objekt',
        ];
        yield 'array returns empty' => [['invalid'], ''];
        yield 'null returns empty' => [null, ''];
    }

    #[Test]
    #[DataProvider('emailInputProvider')]
    public function itSanitizesEmailAddresses(mixed $input, string $expected): void
    {
        $this->assertSame($expected, Sanitizer::email($input));
    }

    public static function emailInputProvider(): Iterator
    {
        yield 'valid email with spaces' => ['  user@example.com  ', 'user@example.com'];
        yield 'email with illegal chars' => ['user(comment)@example.com', 'usercomment@example.com'];
        yield 'scalar int' => [12345, '12345'];
        yield 'stringable email' => [
            new class implements Stringable {
                public function __toString(): string
                {
                    return '  mail@kga.de ';
                }
            },
            'mail@kga.de',
        ];
        yield 'array returns empty' => [['mail@kga.de'], ''];
    }

    #[Test]
    #[DataProvider('normalizeEmailProvider')]
    public function itNormalizesEmailsWithPlusAliasAndGmailDots(mixed $input, string $expected): void
    {
        $this->assertSame($expected, Sanitizer::normalizeEmail($input));
    }

    public static function normalizeEmailProvider(): Iterator
    {
        yield 'non-email without at sign' => ['  KEINE_EMAIL  ', 'keine_email'];
        yield 'standard email keeps dots and strips plus alias' => ['Max.Mustermann+garten12@Example.DE', 'max.mustermann@example.de'];
        yield 'gmail strips dots and plus alias' => ['m.a.x.m+spam@gmail.com', 'maxm@gmail.com'];
        yield 'googlemail maps to gmail and strips dots' => ['first.last@googlemail.com', 'firstlast@gmail.com'];
        yield 'plus at index zero' => ['+onlyalias@example.com', '@example.com'];
    }

    #[Test]
    public function itSanitizesHtmlAllowingSafeElementsAndStrippingScripts(): void
    {
        $this->assertSame('', Sanitizer::html('   '));
        $this->assertSame('', Sanitizer::html(['not-a-string']));
        $this->assertSame('123', Sanitizer::html(123));

        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return '  <strong>Fett</strong>  ';
            }
        };
        $this->assertSame('<strong>Fett</strong>', Sanitizer::html($stringable));

        $dirtyHtml = '  <div class="box" style="color:red">'
            . '<a href="/test" title="LinkTitle" target="_blank" rel="noopener">Klick</a>'
            . '<img src="/pic.webp" alt="AltText" title="ImgTitle" width="100" height="50">'
            . '<script>evil()</script><iframe src="https://evil.com"></iframe>'
            . '</div>  ';

        $clean = Sanitizer::html($dirtyHtml);

        $this->assertStringContainsString('<div class="box" style="color:red">', $clean);
        $this->assertStringContainsString('<a href="/test" title="LinkTitle" target="_blank" rel="noopener">Klick</a>', $clean);
        $this->assertStringContainsString('<img src="/pic.webp" alt="AltText" title="ImgTitle" width="100" height="50"', $clean);
        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
    }

    #[Test]
    #[DataProvider('slugifyProvider')]
    public function itSlugifiesFilenamesCorrectly(string $input, string $expected): void
    {
        $this->assertSame($expected, Sanitizer::slugify($input));
    }

    public static function slugifyProvider(): Iterator
    {
        yield 'umlauts and special chars with uppercase extension' => [
            '---Ärger_mit_Öl_und_Übung_Straße--2026!.PDF',
            'aerger-mit-oel-und-uebung-strasse-2026.pdf',
        ];
        yield 'filename without extension' => [
            'Einfache Datei Ohne Endung',
            'einfache-datei-ohne-endung',
        ];
    }
}
