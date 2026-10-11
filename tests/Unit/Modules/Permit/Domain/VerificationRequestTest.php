<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Permit\Domain;

use App\Modules\Permit\Domain\VerificationRequest;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(VerificationRequest::class)]
final class VerificationRequestTest extends TestCase
{
    #[Test]
    public function itEvaluatesExpirationCorrectly(): void
    {
        $expires = new DateTimeImmutable('2026-10-15 12:00:00');
        $request = new VerificationRequest('token123', $expires, ['data' => 'test']);

        $before = new DateTimeImmutable('2026-10-15 11:59:59');
        $exact = new DateTimeImmutable('2026-10-15 12:00:00');
        $after = new DateTimeImmutable('2026-10-15 12:00:01');

        $this->assertFalse($request->isExpired($before));
        // KILLT MUTANTE (< vs <=): Exakt zum Ablaufzeitpunkt ist es noch nicht abgelaufen!
        $this->assertFalse($request->isExpired($exact));
        $this->assertTrue($request->isExpired($after));
    }
}
