<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\MagicLink;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MagicLink::class)]
final class MagicLinkTest extends TestCase
{
    #[Test]
    public function itCalculatesExpirationCorrectly(): void
    {
        $expires = new DateTimeImmutable('2026-10-11 12:00:00');
        $magicLink = new MagicLink('token', new EmailAddress('test@example.com'), 'CODE', $expires);

        $before = new DateTimeImmutable('2026-10-11 11:59:59');
        $exact = new DateTimeImmutable('2026-10-11 12:00:00');
        $after = new DateTimeImmutable('2026-10-11 12:00:01');

        $this->assertFalse($magicLink->isExpired($before));
        $this->assertFalse($magicLink->isExpired($exact));
        $this->assertTrue($magicLink->isExpired($after));
    }
}
