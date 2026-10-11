<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\System\Infrastructure\System;

use App\Contracts\Config\ConfigInterface;
use App\Modules\System\Infrastructure\System\LocalAssetHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalAssetHelper::class)]
final class LocalAssetHelperTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = \sys_get_temp_dir() . '/kga_asset_test_' . \uniqid();
        @\mkdir($this->tempRoot . '/public/assets', 0o777, true);
    }

    protected function tearDown(): void
    {
        @\unlink($this->tempRoot . '/public/assets/test.css');
        @\rmdir($this->tempRoot . '/public/assets');
        @\rmdir($this->tempRoot . '/public');
        @\rmdir($this->tempRoot);
        parent::tearDown();
    }

    #[Test]
    public function itAppendsFileModificationTimeForCacheBusting(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getBaseUrl')->willReturn('https://app.local');
        $config->method('get')->with('root_path')->willReturn($this->tempRoot);

        $filePath = $this->tempRoot . '/public/assets/test.css';
        \file_put_contents($filePath, 'body { color: red; }');
        $mtime = \filemtime($filePath);

        $helper = new LocalAssetHelper($config);

        // 1. Call: File exists, mtime appended
        $url1 = $helper->url('assets/test.css');
        $this->assertSame("https://app.local/assets/test.css?v={$mtime}", $url1);

        // 2. Call: Cached hit
        $url2 = $helper->url('assets/test.css');
        $this->assertSame("https://app.local/assets/test.css?v={$mtime}", $url2);
    }

    #[Test]
    public function itReturnsUrlWithoutMtimeIfFileNotFound(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getBaseUrl')->willReturn('https://app.local');
        $config->method('get')->with('root_path')->willReturn($this->tempRoot);

        $helper = new LocalAssetHelper($config);

        $url = $helper->url('assets/missing.js');
        $this->assertSame('https://app.local/assets/missing.js', $url);

        // Cached hit for missing file
        $url2 = $helper->url('assets/missing.js');
        $this->assertSame('https://app.local/assets/missing.js', $url2);
    }
}
