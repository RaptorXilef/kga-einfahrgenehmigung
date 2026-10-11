<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Exception;

use App\Application\Exception\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidationException::class)]
final class ValidationExceptionTest extends TestCase
{
    #[Test]
    public function itCreatesExceptionWithStaticConstructor(): void
    {
        $exception = ValidationException::withMessage('Ein Validierungsfehler');

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertSame('Ein Validierungsfehler', $exception->getMessage());
    }
}
