<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\System\Infrastructure\System;

use App\Contracts\Config\ConfigInterface;
use App\Modules\System\Infrastructure\System\SystemInfoService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemInfoService::class)]
final class SystemInfoServiceTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = \sys_get_temp_dir() . '/kga_sysinfo_' . \uniqid();
        @\mkdir($this->tempRoot);

        \file_put_contents($this->tempRoot . '/package.json', '{"version": "1.2.3"}');

        $changelog = "## [1.2.3] - 2026-01-01\n### Added\n- Feature A\n\n## [1.2.2] - 2025-12-31\n### Fixed\n- Bug B";
        \file_put_contents($this->tempRoot . '/CHANGELOG.md', $changelog);
    }

    protected function tearDown(): void
    {
        @\unlink($this->tempRoot . '/package.json');
        @\unlink($this->tempRoot . '/CHANGELOG.md');
        @\rmdir($this->tempRoot);
        parent::tearDown();
    }

    #[Test]
    public function itParsesVersionAndChangelogCorrectly(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('get')->willReturn($this->tempRoot);

        $service = new SystemInfoService($config);

        $this->assertSame('v1.2.3', $service->getCurrentVersion());
        $this->assertStringContainsString('Feature A', $service->getChangelog());

        $all = $service->getAllReleaseNotes();
        $this->assertCount(2, $all);
        $this->assertSame('1.2.3', $all[0]['clean_version']);
        $this->assertSame('1.2.2', $all[1]['clean_version']);

        $unread = $service->getUnreadReleaseNotes('v1.2.2');
        $this->assertCount(1, $unread);
        $this->assertSame('1.2.3', $unread[0]['clean_version']);
    }

    #[Test]
    public function itHandlesMissingFilesGracefully(): void
    {
        @\unlink($this->tempRoot . '/package.json');
        @\unlink($this->tempRoot . '/CHANGELOG.md');

        $config = $this->createStub(ConfigInterface::class);
        $config->method('get')->willReturn($this->tempRoot);

        $service = new SystemInfoService($config);

        $this->assertSame('v0.0.0', $service->getCurrentVersion());
        $this->assertSame('', $service->getChangelog());
        $this->assertSame([], $service->getAllReleaseNotes());
    }
}
