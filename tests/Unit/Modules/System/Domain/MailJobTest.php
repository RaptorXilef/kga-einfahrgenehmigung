<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\System\Domain;

use App\Modules\System\Domain\MailJob;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MailJob::class)]
final class MailJobTest extends TestCase
{
    #[Test]
    public function itCorrectlyStoresTheValues(): void
    {
        $now = new DateTimeImmutable();
        $template = new TemplateKey('std_7');
        $job = new MailJob('job_1', 'test@test.de', 'reply@test.de', 'Subject', $template, ['a' => 1], 0, $now, 100);

        $this->assertSame('job_1', $job->id);
        $this->assertSame('test@test.de', $job->recipient);
        $this->assertSame('reply@test.de', $job->replyTo);
        $this->assertSame('Subject', $job->subject);
        $this->assertSame($template, $job->template);
        $this->assertSame(['a' => 1], $job->data);
        $this->assertSame(0, $job->attempts);
        $this->assertSame($now, $job->createdAt);
        $this->assertSame(100, $job->priority);
    }
}
