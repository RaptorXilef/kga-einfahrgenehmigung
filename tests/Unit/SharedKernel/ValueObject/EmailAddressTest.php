<?php

declare(strict_types=1);

use App\SharedKernel\Domain\ValueObject\EmailAddress;
use InvalidArgumentException;

\covers(EmailAddress::class);

\test('it accepts and normalizes valid email addresses', function (string $input, string $expected): void {
    $email = new EmailAddress($input);

    \expect($email->value)->toBe($expected)
        ->and((string) $email)->toBe($expected);
})->with([
    'standard email' => ['test@example.com', 'test@example.com'],
    'uppercase to lower' => ['TEST@EXAMPLE.COM', 'test@example.com'],
    'with leading spaces' => ['  user@domain.de', 'user@domain.de'],
    'with trailing spaces' => ['user@domain.de  ', 'user@domain.de'],
    'complex valid' => ['first.last+alias@sub.domain.co.uk', 'first.last+alias@sub.domain.co.uk'],
]);

\test('it throws exception for invalid email addresses', function (string $invalidInput): void {
    new EmailAddress($invalidInput);
})->with([
    'empty string' => '',
    'spaces only' => '   ',
    'missing at sign' => 'testexample.com',
    'missing domain' => 'test@',
    'missing user' => '@example.com',
    'invalid chars' => 'test @example.com',
])->throws(InvalidArgumentException::class);

\test('it correctly compares two email addresses for equality', function (): void {
    $email1 = new EmailAddress('test@example.com');
    $email2 = new EmailAddress(' TEST@example.com ');
    $email3 = new EmailAddress('other@example.com');

    \expect($email1->equals($email2))->toBeTrue()
        ->and($email1->equals($email3))->toBeFalse();
});
