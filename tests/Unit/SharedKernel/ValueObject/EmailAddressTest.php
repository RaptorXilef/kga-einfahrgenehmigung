<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\ValueObject;

use App\SharedKernel\Domain\ValueObject\EmailAddress;
use InvalidArgumentException;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmailAddress::class)]
final class EmailAddressTest extends TestCase
{
    #[Test]
    #[DataProvider('validEmailProvider')]
    public function itAcceptsAndNormalizesValidEmailAddresses(string $input, string $expected): void
    {
        $email = new EmailAddress($input);
        $this->assertSame($expected, $email->value);
        $this->assertSame($expected, (string) $email);
    }

    public static function validEmailProvider(): Iterator
    {
        yield 'standard email' => ['test@example.com', 'test@example.com'];
        yield 'uppercase to lower' => ['TEST@EXAMPLE.COM', 'test@example.com'];
        yield 'with leading spaces' => ['  user@domain.de', 'user@domain.de'];
        yield 'complex valid' => ['first.last+alias@sub.domain.co.uk', 'first.last+alias@sub.domain.co.uk'];
    }

    #[Test]
    #[DataProvider('invalidEmailProvider')]
    public function itThrowsExceptionForInvalidEmailAddresses(string $invalidInput): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EmailAddress($invalidInput);
    }

    public static function invalidEmailProvider(): Iterator
    {
        yield 'empty string' => [''];
        yield 'spaces only' => ['   '];
        yield 'missing at sign' => ['testexample.com'];
        yield 'invalid chars' => ['test @example.com'];
    }

    #[Test]
    public function itCorrectlyComparesTwoEmailAddressesForEquality(): void
    {
        $email1 = new EmailAddress('test@example.com');
        $email2 = new EmailAddress(' TEST@example.com ');
        $email3 = new EmailAddress('other@example.com');

        $this->assertTrue($email1->equals($email2));
        $this->assertFalse($email1->equals($email3));
    }
}
