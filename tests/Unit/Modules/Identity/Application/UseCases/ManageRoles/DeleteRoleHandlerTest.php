<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\ManageRoles;

use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Identity\Application\UseCases\ManageRoles\DeleteRoleCommand;
use App\Modules\Identity\Application\UseCases\ManageRoles\DeleteRoleHandler;
use App\Modules\Identity\Domain\Events\RoleDeletedEvent;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteRoleHandler::class)]
final class DeleteRoleHandlerTest extends TestCase
{
    #[Test]
    public function itDeletesRoleAndDispatchesEvent(): void
    {
        $role = new Role('role_test', 'Test Role', []);
        $repo = $this->createMock(RoleRepositoryInterface::class);
        $repo->expects($this->once())->method('findById')->with('role_test')->willReturn($role);
        $repo->expects($this->once())->method('delete')->with('role_test');

        $now = new DateTimeImmutable();
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())->method('dispatch')->willReturnCallback(
            function (RoleDeletedEvent $event) use ($now): void {
                $this->assertSame('role_test', $event->roleId);
                $this->assertSame($now, $event->occurredOn);
            },
        );

        $handler = new DeleteRoleHandler($repo, $dispatcher, $clock);
        $deletedName = $handler->handle(new DeleteRoleCommand('role_test'));

        $this->assertSame('Test Role', $deletedName);
    }

    #[Test]
    public function itThrowsExceptionIfTryingToDeleteAdminRole(): void
    {
        $handler = new DeleteRoleHandler(
            $this->createStub(RoleRepositoryInterface::class),
            $this->createStub(EventDispatcherInterface::class),
            new SystemClock(),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Die Admin-Rolle kann nicht gelöscht werden.');

        $handler->handle(new DeleteRoleCommand('admin'));
    }

    #[Test]
    public function itThrowsExceptionIfRoleNotFound(): void
    {
        $repo = $this->createStub(RoleRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $handler = new DeleteRoleHandler(
            $repo,
            $this->createStub(EventDispatcherInterface::class),
            new SystemClock(),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Rolle nicht gefunden.');

        $handler->handle(new DeleteRoleCommand('unknown'));
    }
}
