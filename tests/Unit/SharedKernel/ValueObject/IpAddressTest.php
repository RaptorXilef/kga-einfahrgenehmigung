<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\ValueObject;

use App\SharedKernel\Domain\ValueObject\IpAddress;
use InvalidArgumentException;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IpAddress::class)]
final class IpAddressTest extends TestCase
{
    #[Test]
    #[DataProvider('validIpProvider')]
    public function itAcceptsValidIPv4AndIPv6Addresses(string $input, string $expected): void
    {
        $ip = new IpAddress($input);

        $this->assertSame($expected, $ip->value);
        $this->assertSame($expected, (string) $ip);
    }

    public static function validIpProvider(): Iterator
    {
        yield 'standard IPv4' => ['192.168.1.1', '192.168.1.1'];
        yield 'localhost v4' => ['127.0.0.1', '127.0.0.1'];
        yield 'standard IPv6' => ['2001:0db8:85a3:0000:0000:8a2e:0370:7334', '2001:0db8:85a3:0000:0000:8a2e:0370:7334'];
        yield 'localhost v6' => ['::1', '::1'];
        // KILLT MUTANTE 7: Sichert das trim() bei der Eingabe ab
        yield 'with spaces' => ['  192.168.1.1  ', '192.168.1.1'];
    }

    #[Test]
    #[DataProvider('invalidIpProvider')]
    public function itThrowsExceptionForInvalidIPFormats(string $invalidInput, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        // KILLT MUTANTE 8: Wirft der Mutator das 'throw' weg, wird der Fehler erst im FILTER_VALIDATE geworfen.
        // Das ergibt eine andere Exception Message, die wir hier gezielt abfragen.
        $this->expectExceptionMessage($expectedMessage);
        new IpAddress($invalidInput);
    }

    public static function invalidIpProvider(): Iterator
    {
        yield 'empty string' => ['   ', 'IP-Adresse darf nicht leer sein.'];
        yield 'invalid format' => ['192.168.1', 'Ungültiges IP-Adressen-Format: 192.168.1'];
        yield 'out of range v4' => ['256.256.256.256', 'Ungültiges IP-Adressen-Format: 256.256.256.256'];
        yield 'text instead IP' => ['localhost', 'Ungültiges IP-Adressen-Format: localhost'];
    }
}
