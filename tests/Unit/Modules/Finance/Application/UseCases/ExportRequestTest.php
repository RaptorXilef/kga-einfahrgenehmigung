<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Finance\Application\UseCases;

use App\Application\Http\ServerRequest;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Finance\Application\UseCases\ExportFinanceData\ExportRequest;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExportRequest::class)]
final class ExportRequestTest extends TestCase
{
    #[Test]
    public function itResolvesParametersFromPostAndSession(): void
    {
        $request = new ServerRequest(post: ['format' => 'json', 'start' => 'all'], server: ['REQUEST_METHOD' => 'POST']);
        $sessionFilters = ['start' => '2026-01-01', 'end' => '2026-12-31'];
        $clock = $this->createStub(ClockInterface::class);

        $dto = ExportRequest::fromRequest($request, $sessionFilters, $clock);

        $this->assertSame('json', $dto->format);
        $this->assertSame('2026-01-01', $dto->start); // Aus Session gezogen
        $this->assertSame('2026-12-31', $dto->end);   // Default Fallback auf Session
    }

    #[Test]
    public function itUsesClockFallbacksIfNothingIsProvided(): void
    {
        $request = new ServerRequest(get: []); // GET Request
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-15'));

        $dto = ExportRequest::fromRequest($request, [], $clock);

        $this->assertSame('csv', $dto->format);
        $this->assertSame('2026-01-01', $dto->start);
        $this->assertSame('2026-12-31', $dto->end);
    }
}
