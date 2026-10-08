<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Event;

use App\SharedKernel\Infrastructure\Event\EventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(EventDispatcher::class)]
final class EventDispatcherTest extends TestCase
{
    #[Test]
    public function itDispatchesEventsToAllRegisteredListenersInOrder(): void
    {
        $dispatcher = new EventDispatcher();
        $callLog = [];

        $event = new stdClass();
        $event->payload = 'test_event';

        $dispatcher->addListener(stdClass::class, static function (stdClass $e) use (&$callLog): void {
            $callLog[] = 'listener_1:' . $e->payload;
        });

        $dispatcher->addListener(stdClass::class, static function (stdClass $e) use (&$callLog): void {
            $callLog[] = 'listener_2:' . $e->payload;
        });

        $dispatcher->dispatch($event);

        $this->assertSame(['listener_1:test_event', 'listener_2:test_event'], $callLog);
    }

    #[Test]
    public function itSafelyIgnoresEventsWithoutRegisteredListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->dispatch(new stdClass());

        $this->expectNotToPerformAssertions();
    }
}
