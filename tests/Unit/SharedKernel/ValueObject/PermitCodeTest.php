<?php

declare(strict_types=1);

use App\SharedKernel\Domain\ValueObject\PermitCode;

\covers(PermitCode::class);

\test('it accepts and normalizes valid permit codes', function (string $input, string $expected): void {
    $code = new PermitCode($input);

    \expect($code->value)->toBe($expected);
})->with([
    'standard code' => ['A1B2C3D4', 'A1B2C3D4'],
    'lowercase code' => ['a1b2c3d4', 'A1B2C3D4'],
    'with leading spaces' => ['  CODE123', 'CODE123'],
    'with dashes' => ['ML-0020-B-1234', 'ML-0020-B-1234'], // Dashes are preserved in the DB format
]);

\test('it throws exception for empty permit codes', function (string $invalidInput): void {
    new PermitCode($invalidInput);
})->with([
    'empty string' => '',
    'spaces only' => '   ',
])->throws(\InvalidArgumentException::class, 'Der Permit-Code darf nicht leer sein.');
