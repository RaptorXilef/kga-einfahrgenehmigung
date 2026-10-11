<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\System\Application\UseCases\ManageMails;

use App\Contracts\Mail\MailLogEntry;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Modules\System\Application\UseCases\ManageMails\ResendMailCommand;
use App\Modules\System\Application\UseCases\ManageMails\ResendMailHandler;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResendMailHandler::class)]
final class ResendMailHandlerTest extends TestCase
{
    #[Test]
    public function itRequeuesMailAndReturnsResult(): void
    {
        $entry = new MailLogEntry(
            'ml_123',
            new DateTimeImmutable(),
            'test@example.com',
            null,
            'Subject',
            new TemplateKey('std_7'),
            'Fehler',
            ['data' => 'yes'],
        );

        $log = $this->createMock(MailLogInterface::class);
        $log->expects($this->once())->method('findByTimestamp')->with('2026-10-15')->willReturn($entry); // N-Fix

        $mailer = $this->createMock(MailServiceInterface::class);
        $mailer->expects($this->once())->method('sendTemplate')->with('test@example.com', 'Subject', 'std_7', ['data' => 'yes'], null, 100);

        $handler = new ResendMailHandler($log, $mailer);
        $result = $handler->handle(new ResendMailCommand('2026-10-15'));

        $this->assertSame('test@example.com', $result->recipient);
        $this->assertSame('Subject', $result->subject);
    }

    #[Test]
    public function itThrowsExceptionIfEntryNotFoundOrDataMissing(): void
    {
        $log = $this->createStub(MailLogInterface::class);
        $log->method('findByTimestamp')->willReturn(null);

        $handler = new ResendMailHandler($log, $this->createStub(MailServiceInterface::class));

        $this->expectException(DomainException::class);
        $handler->handle(new ResendMailCommand('2026'));
    }
}
