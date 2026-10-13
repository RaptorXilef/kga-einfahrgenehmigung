<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Routing;

use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Routing\ActionRegistry;
use App\Application\Routing\UniversalActionFactory;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\DependencyInjection\ContainerInterface;
use App\Contracts\System\RouteCacheInterface;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// Dummy-Action für den class_exists Check in der Factory
final class DummyActionTestClass implements ActionInterface
{
    public function execute(ServerRequest $request): ResponseInterface
    {
        throw new Exception('Sollte nicht aufgerufen werden');
    }
}

#[CoversClass(UniversalActionFactory::class)]
final class UniversalActionFactoryTest extends TestCase
{
    private function getActionRegistryStub(): ActionRegistry
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getString')->willReturnCallback(fn (string $k, string $def = '') => $k === 'root_path' ? \sys_get_temp_dir() . '/kga_empty_dir_' . \uniqid() : $def);
        $config->method('getBool')->willReturn(true);

        return new ActionRegistry($config, $this->createStub(RouteCacheInterface::class));
    }

    #[Test]
    public function itReturnsActionInstanceIfFoundInContainer(): void
    {
        $actionMock = new DummyActionTestClass();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
                  ->method('get')
                  ->with(DummyActionTestClass::class)
                  ->willReturn($actionMock);

        $factory = new UniversalActionFactory($this->getActionRegistryStub(), $container);

        $result = $factory->create(DummyActionTestClass::class);

        $this->assertSame($actionMock, $result);
    }

    #[Test]
    public function itReturnsNullIfClassDoesNotExist(): void
    {
        $factory = new UniversalActionFactory(
            $this->getActionRegistryStub(),
            $this->createStub(ContainerInterface::class),
        );

        $result = $factory->create('App\\NonExistent\\Class\\Action');

        $this->assertNull($result);
    }
}
