<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\System\Application\UseCases\ManageBackups;

use App\Contracts\Storage\BackupServiceInterface;
use App\Modules\System\Application\UseCases\ManageBackups\TruncateTargetCommand;
use App\Modules\System\Application\UseCases\ManageBackups\TruncateTargetHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TruncateTargetHandler::class)]
final class TruncateTargetHandlerTest extends TestCase
{
    #[Test]
    public function itDelegatesTruncationToBackupService(): void
    {
        $service = $this->createMock(BackupServiceInterface::class);
        $service->expects($this->once())->method('truncateTarget')->with('mail_log')->willReturn('mail_logs_table');

        $handler = new TruncateTargetHandler($service);
        $result = $handler->handle(new TruncateTargetCommand('mail_log'));

        $this->assertSame('mail_logs_table', $result);
    }
}
