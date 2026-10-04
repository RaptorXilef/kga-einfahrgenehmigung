<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;

\covers(Role::class);

\test('it creates a role and allows retrieving its properties', function (): void {
    $role = new Role('role_123', 'Vorstand', ['permits.view', 'system.manage']);

    \expect($role->id)->toBe('role_123')
        ->and($role->getName())->toBe('Vorstand')
        ->and($role->getPermissions())->toBe(['permits.view', 'system.manage']);
});

\test('it allows renaming the role', function (): void {
    $role = new Role('role_123', 'Old Name', []);
    $role->rename('New Admin Name');

    \expect($role->getName())->toBe('New Admin Name');
});

\test('it throws exception when renaming to an empty string', function (): void {
    $role = new Role('role_123', 'Valid Name', []);
    $role->rename('   ');
})->throws(DomainException::class, 'Der Rollenname darf nicht leer sein.');

\test('it allows updating permissions', function (): void {
    $role = new Role('role_123', 'Name', ['old.perm']);
    $role->updatePermissions(['new.perm1', 'new.perm2']);

    \expect($role->getPermissions())->toBe(['new.perm1', 'new.perm2']);
});
