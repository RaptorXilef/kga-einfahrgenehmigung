<?php

declare(strict_types=1);

use App\SharedKernel\Domain\ValueObject\IpAddress;

\covers(IpAddress::class);

\test('it accepts valid IPv4 and IPv6 addresses', function (string $input): void {
    $ip = new IpAddress($input);

    \expect($ip->value)->toBe($input)
        ->and((string) $ip)->toBe($input);
})->with([
    'standard IPv4' => ['192.168.1.1'],
    'localhost v4' => ['127.0.0.1'],
    'standard IPv6' => ['2001:0db8:85a3:0000:0000:8a2e:0370:7334'],
    'localhost v6' => ['::1'],
]);

\test('it throws exception for invalid IP formats', function (string $invalidInput): void {
    new IpAddress($invalidInput);
})->with([
    'empty string' => '   ',
    'invalid format' => '192.168.1',
    'out of range v4' => '256.256.256.256',
    'text instead IP' => 'localhost',
])->throws(\InvalidArgumentException::class);
