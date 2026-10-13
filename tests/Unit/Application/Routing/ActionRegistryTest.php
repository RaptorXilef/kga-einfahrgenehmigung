<?php

declare(strict_types=1);

namespace App\Modules\TestMod\Application\UseCases {
use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;

    #[Route('GET', '/exact-path')]
    #[RequiresAuth]
    class MyExactAction
    {
    }

    #[Route('POST', '/api/users/{id}/update')]
    class MyDynamicAction
    {
    }
}

namespace App\Tests\Unit\Application\Routing {
    use App\Application\Routing\ActionRegistry;
    use App\Contracts\Config\ConfigInterface;
    use App\Contracts\System\RouteCacheInterface;
    use PHPUnit\Framework\Attributes\CoversClass;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    #[CoversClass(ActionRegistry::class)]
    final class ActionRegistryTest extends TestCase
    {
        private string $tempRoot;

        protected function setUp(): void
        {
            parent::setUp();

            $sysTemp = \sys_get_temp_dir();
            $this->tempRoot = $sysTemp . \DIRECTORY_SEPARATOR . 'kga_registry_' . \uniqid();

            $moduleDir = $this->tempRoot . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . 'Modules' . \DIRECTORY_SEPARATOR . 'TestMod' . \DIRECTORY_SEPARATOR . 'Application' . \DIRECTORY_SEPARATOR . 'UseCases';
            @\mkdir($moduleDir, 0o777, true);

            // WICHTIG: realpath() nach dem Erstellen des Ordners aufrufen, um die exakte Groß-/Kleinschreibung
            // des Betriebssystems (Windows) zu erhalten! Sonst schlägt das str_replace im Scanner fehl.
            $this->tempRoot = \realpath($this->tempRoot) ?: $this->tempRoot;
            $moduleDir = \realpath($moduleDir) ?: $moduleDir;

            \file_put_contents($moduleDir . \DIRECTORY_SEPARATOR . 'MyExactAction.php', '<?php');
            \file_put_contents($moduleDir . \DIRECTORY_SEPARATOR . 'MyDynamicAction.php', '<?php');
        }

        protected function tearDown(): void
        {
            $moduleDir = $this->tempRoot . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . 'Modules' . \DIRECTORY_SEPARATOR . 'TestMod' . \DIRECTORY_SEPARATOR . 'Application' . \DIRECTORY_SEPARATOR . 'UseCases';
            @\unlink($moduleDir . \DIRECTORY_SEPARATOR . 'MyExactAction.php');
            @\unlink($moduleDir . \DIRECTORY_SEPARATOR . 'MyDynamicAction.php');
            @\rmdir($moduleDir);
            @\rmdir($this->tempRoot . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . 'Modules' . \DIRECTORY_SEPARATOR . 'TestMod' . \DIRECTORY_SEPARATOR . 'Application');
            @\rmdir($this->tempRoot . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . 'Modules' . \DIRECTORY_SEPARATOR . 'TestMod');
            @\rmdir($this->tempRoot . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . 'Modules');
            @\rmdir($this->tempRoot . \DIRECTORY_SEPARATOR . 'src');
            @\rmdir($this->tempRoot);
            parent::tearDown();
        }

        #[Test]
        public function itScansDirectoriesAndRegistersExactAndDynamicRoutes(): void
        {
            $config = $this->createStub(ConfigInterface::class);
            $config->method('getString')->willReturnCallback(fn (string $k, string $def = '') => $k === 'root_path' ? $this->tempRoot : $def);
            $config->method('getBool')->willReturn(true); // debug_mode = true erzwingt den Live-Scan (Ignoriert Cache)

            $cache = $this->createMock(RouteCacheInterface::class);
            $cache->expects($this->once())->method('clearOld');
            $cache->expects($this->once())->method('save');

            $registry = new ActionRegistry($config, $cache);

            // Teste die exakte Route
            $exactMatch = $registry->match('GET', '/exact-path');
            $this->assertNotNull($exactMatch);
            $this->assertSame('App\Modules\TestMod\Application\UseCases\MyExactAction', $exactMatch['class']);
            $this->assertTrue($exactMatch['requiresAuth']);
            $this->assertSame([], $exactMatch['params']);

            // Teste die dynamische Regex Route
            $dynMatch = $registry->match('POST', '/api/users/99/update');
            $this->assertNotNull($dynMatch);
            $this->assertSame('App\Modules\TestMod\Application\UseCases\MyDynamicAction', $dynMatch['class']);
            $this->assertFalse($dynMatch['requiresAuth']);
            $this->assertSame(['id' => '99'], $dynMatch['params']);

            // Teste Fallback bei ungültiger Route
            $this->assertNull($registry->match('GET', '/unknown'));
        }

        #[Test]
        public function itLoadsFromCacheIfDebugModeIsOff(): void
        {
            $config = $this->createStub(ConfigInterface::class);
            $config->method('getBool')->willReturn(false); // debug_mode = false

            $cachedRoutes = [
                'exact' => [
                    'GET' => [
                        '/cached' => ['class' => 'CachedAction', 'auth' => true],
                    ],
                ],
                'dynamic' => [],
            ];

            $cache = $this->createMock(RouteCacheInterface::class);
            $cache->expects($this->once())->method('load')->willReturn($cachedRoutes);
            $cache->expects($this->never())->method('save'); // Darf im Cache-Mode nicht speichern/scannen

            $registry = new ActionRegistry($config, $cache);
            $match = $registry->match('GET', '/cached');

            $this->assertNotNull($match);
            $this->assertSame('CachedAction', $match['class']);
        }
    }
}
