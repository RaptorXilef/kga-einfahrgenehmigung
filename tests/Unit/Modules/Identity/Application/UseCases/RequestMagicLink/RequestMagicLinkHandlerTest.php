<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\RequestMagicLink;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Identity\Application\UseCases\RequestMagicLink\RequestMagicLinkCommand;
use App\Modules\Identity\Application\UseCases\RequestMagicLink\RequestMagicLinkHandler;
use App\Modules\Identity\Domain\Events\MagicLinkRequestedEvent;
use App\Modules\Identity\Domain\MagicLink;
use App\Modules\Identity\Domain\MagicLinkRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestMagicLinkHandler::class)]
final class RequestMagicLinkHandlerTest extends TestCase
{
    #[Test]
    public function itGeneratesTokenAndSavesMagicLinkAndDispatchesEvent(): void
    {
        $now = new DateTimeImmutable('2026-10-15 12:00:00');
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $config = $this->createStub(ConfigInterface::class);
        // Link soll 15 Minuten gültig sein
        $config->method('getInt')->with('magic_link_duration', 15)->willReturn(15);

        $repository = $this->createMock(MagicLinkRepositoryInterface::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        // Erwartet, dass das Repository mit einem sauber berechneten Ablaufdatum aufgerufen wird
        $repository->expects($this->once())->method('save')->willReturnCallback(
            function (MagicLink $magicLink): void {
                $this->assertSame('test@example.com', $magicLink->email->value);
                $this->assertSame(64, \strlen($magicLink->token)); // 32 bytes hex
                $this->assertSame(6, \strlen($magicLink->code));
                $this->assertSame('2026-10-15 12:15:00', $magicLink->expiresAt->format('Y-m-d H:i:s'));
            },
        );

        // Erwartet das korrekte Domain-Event
        $eventDispatcher->expects($this->once())->method('dispatch')->willReturnCallback(
            function (MagicLinkRequestedEvent $event) use ($now): void {
                $this->assertSame('test@example.com', $event->email);
                $this->assertSame($now, $event->occurredOn);
                $this->assertSame(64, \strlen($event->token));
            },
        );

        $handler = new RequestMagicLinkHandler($repository, $eventDispatcher, $config, $clock);
        $handler->handle(new RequestMagicLinkCommand('test@example.com'));
    }
}
