<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\ValueObject;

use App\SharedKernel\Domain\ValueObject\IpAddress;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IpAddress::class)]
final class IpAddressTest extends TestCase
{
    #[Test]
    #[DataProvider('validIpProvider')]
    public function itAcceptsValidIPv4AndIPv6Addresses(string $input): void
    {
        $ip = new IpAddress($input);

        $this->assertSame($input, $ip->value);
        $this->assertSame($input, (string) $ip);
    }

    public static function validIpProvider(): array
    {
        return [
            'standard IPv4' => ['192.168.1.1'],
            'localhost v4' => ['127.0.0.1'],
            'standard IPv6' => ['2001:0db8:85a3:0000:0000:8a2e:0370:7334'],
            'localhost v6' => ['::1'],
        ];
    }

    #[Test]
    #[DataProvider('invalidIpProvider')]
    public function itThrowsExceptionForInvalidIPFormats(string $invalidInput): void
    {
        $this->expectException(InvalidArgumentException::class);
        new IpAddress($invalidInput);
    }

    public static function invalidIpProvider(): array
    {
        return [
            'empty string' => ['   '],
            'invalid format' => ['192.168.1'],
            'out of range v4' => ['256.256.256.256'],
            'text instead IP' => ['localhost'],
        ];
    }
}
