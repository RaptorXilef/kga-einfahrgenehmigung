<?php

declare(strict_types=1);

use App\SharedKernel\Domain\ValueObject\TemplateKey;

\covers(TemplateKey::class);

\test('it accepts and normalizes valid template keys', function (string $input, string $expected): void {
    $key = new TemplateKey($input);

    \expect($key->value)->toBe($expected);
})->with([
    'standard key'        => ['std_7', 'std_7'],
    'uppercase to lower'  => ['PERM_12', 'perm_12'],
    'legacy dot format'   => ['std.14', 'std_14'], // Self-healing behavior
    'with spaces'         => ['  custom_perm  ', 'custom_perm'],
]);

\test('it throws exception for empty or invalid template keys', function (string $invalidInput): void {
    new TemplateKey($invalidInput);
})->with([
    'empty string'       => '',
    'spaces only'        => '   ',
    'special chars'      => 'std_7!',
    'invalid formatting' => 'my/template',
])->throws(InvalidArgumentException::class);
