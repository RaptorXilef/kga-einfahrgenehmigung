<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\VerifyMagicLink;

use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Identity\Application\UseCases\VerifyMagicLink\VerifyMagicLinkCommand;
use App\Modules\Identity\Application\UseCases\VerifyMagicLink\VerifyMagicLinkHandler;
use App\Modules\Identity\Domain\MagicLink;
use App\Modules\Identity\Domain\MagicLinkRepositoryInterface;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(VerifyMagicLinkHandler::class)]
final class VerifyMagicLinkHandlerTest extends TestCase
{
    #[Test]
    public function itSuccessfullyVerifiesAndLogsUserIn(): void
    {
        $now = new DateTimeImmutable('2026-10-15 12:00:00');
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        // Gutschein ist noch 1 Minute gültig
        $validMagicLink = new MagicLink(
            'token123',
            new EmailAddress('user@example.com'),
            'CODE12',
            new DateTimeImmutable('2026-10-15 12:01:00'),
        );

        $repository = $this->createMock(MagicLinkRepositoryInterface::class);
        // Passive Garbage Collection erzwingen
        $repository->expects($this->once())->method('deleteExpired')->with($now);
        // 100% Trim + UpperCase Test (Killt Mutanten)
        $repository->expects($this->once())->method('findByInput')->with('CODE12')->willReturn($validMagicLink);
        // Single-Use erzwingen
        $repository->expects($this->once())->method('delete')->with('token123');

        $session = $this->createMock(AuthSessionInterface::class);
        $session->expects($this->once())->method('regenerate');
        $session->expects($this->once())->method('setHistoryEmail')->with('user@example.com');

        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects($this->once())->method('clearAttempts')->with('127.0.0.1');

        $handler = new VerifyMagicLinkHandler($repository, $session, $rateLimiter, $clock);

        // Input absichtlich mit Whitespace und Lowercase (Testet strtoupper(trim()))
        $handler->handle(new VerifyMagicLinkCommand('  code12  ', '127.0.0.1'));
    }

    #[Test]
    public function itThrowsExceptionAndRecordsStrikeIfLinkIsNotFoundOrExpired(): void
    {
        $now = new DateTimeImmutable('2026-10-15 12:00:00');
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        // Abgelaufen (gestern)
        $expiredLink = new MagicLink(
            'token123',
            new EmailAddress('user@example.com'),
            'CODE12',
            new DateTimeImmutable('2026-10-14 12:00:00'),
        );

        $repository = $this->createStub(MagicLinkRepositoryInterface::class);
        $repository->method('findByInput')->willReturn($expiredLink);

        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        // Erwartet einen Strike gegen die IP
        $rateLimiter->expects($this->once())->method('recordFailedAttempt')->with('10.0.0.5');

        $handler = new VerifyMagicLinkHandler(
            $repository,
            $this->createStub(AuthSessionInterface::class),
            $rateLimiter,
            $clock,
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Der Code oder Link ist ungültig oder abgelaufen.');

        $handler->handle(new VerifyMagicLinkCommand('CODE12', '10.0.0.5'));
    }
}
