<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\System\Infrastructure\System;

use App\Contracts\Config\ConfigInterface;
use App\Modules\System\Infrastructure\System\FileRouteCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileRouteCache::class)]
final class FileRouteCacheTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = \sys_get_temp_dir() . '/kga_route_cache_' . \uniqid();
        @\mkdir($this->tempRoot . '/cache', 0o777, true);
    }

    protected function tearDown(): void
    {
        @\unlink($this->tempRoot . '/cache/routes_v2.php');
        @\unlink($this->tempRoot . '/cache/routes.php');
        @\rmdir($this->tempRoot . '/cache');
        @\rmdir($this->tempRoot);
        parent::tearDown();
    }

    #[Test]
    public function itSavesAndLoadsRouteCacheCorrectly(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('get')->willReturn($this->tempRoot); // D-Fix: Kein with() bei Stubs

        $cache = new FileRouteCache($config);

        $this->assertNull($cache->load()); // Initial leer

        $dummyRoutes = ['exact' => ['GET' => ['/' => ['class' => 'Home', 'auth' => false]]], 'dynamic' => []];
        $cache->save($dummyRoutes);

        $loaded = $cache->load();
        $this->assertSame($dummyRoutes, $loaded);
    }

    #[Test]
    public function itClearsOldAndAllCaches(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('get')->willReturn($this->tempRoot); // D-Fix

        // Erstelle künstliche Cache-Dateien
        \file_put_contents($this->tempRoot . '/cache/routes_v2.php', '<?php return [];');
        \file_put_contents($this->tempRoot . '/cache/routes.php', '<?php return [];');

        $cache = new FileRouteCache($config);

        $cache->clearOld();
        $this->assertFileDoesNotExist($this->tempRoot . '/cache/routes.php');
        $this->assertFileExists($this->tempRoot . '/cache/routes_v2.php');

        $cache->clearAll();
        $this->assertFileDoesNotExist($this->tempRoot . '/cache/routes_v2.php');
    }
}
