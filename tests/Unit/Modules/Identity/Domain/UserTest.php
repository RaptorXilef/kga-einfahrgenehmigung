<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\User;

\covers(User::class);

\test('it creates a user and allows retrieving properties', function (): void {
    $user = new User('usr_123', 'max_m', 'role_admin', 'hash123', 'v1.0.0');

    \expect($user->id)->toBe('usr_123')
        ->and($user->getUsername())->toBe('max_m')
        ->and($user->getRoleId())->toBe('role_admin')
        ->and($user->getPasswordHash())->toBe('hash123')
        ->and($user->getLastSeenChangelog())->toBe('v1.0.0');
});

\test('it verifies and changes password correctly', function (): void {
    $hash = \password_hash('OldP4ssw0rd!', \PASSWORD_DEFAULT);
    $user = new User('usr_1', 'admin', 'role_admin', $hash, 'v0.0.0');

    // Altes Passwort stimmt
    \expect($user->verifyPassword('OldP4ssw0rd!'))->toBeTrue()
        ->and($user->verifyPassword('WrongPass'))->toBeFalse();

    // Passwort ändern
    $user->changePassword('NewP4ssw0rd!');

    // Neues Passwort stimmt, altes nicht mehr
    \expect($user->verifyPassword('NewP4ssw0rd!'))->toBeTrue()
        ->and($user->verifyPassword('OldP4ssw0rd!'))->toBeFalse();
});

\test('it throws exception for too short passwords', function (): void {
    $user = new User('usr_1', 'admin', 'role_admin', 'hash', 'v0.0.0');
    $user->changePassword('short1!');
})->throws(DomainException::class, 'Das neue Passwort muss mindestens 8 Zeichen lang sein.');

\test('it allows renaming and updating role', function (): void {
    $user = new User('usr_1', 'old_name', 'role_1', 'hash', 'v0');

    $user->rename('new_name');
    $user->changeRole('role_2');

    \expect($user->getUsername())->toBe('new_name')
        ->and($user->getRoleId())->toBe('role_2');
});

\test('it throws exceptions for empty names and roles', function (string $method, string $invalidValue): void {
    $user = new User('usr_1', 'name', 'role', 'hash', 'v0');
    $user->$method($invalidValue);
})->with([
    'empty name' => ['rename', '   '],
    'empty role' => ['changeRole', ''],
])->throws(DomainException::class);

\test('it updates the last seen changelog version', function (): void {
    $user = new User('usr_1', 'name', 'role', 'hash', 'v1.0.0');
    $user->markChangelogAsRead('v1.5.0');

    \expect($user->getLastSeenChangelog())->toBe('v1.5.0');
});
