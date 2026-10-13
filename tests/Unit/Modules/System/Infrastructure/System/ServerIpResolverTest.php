<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\System\Infrastructure\System;

use App\Modules\System\Infrastructure\System\ServerIpResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerIpResolver::class)]
final class ServerIpResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CLIENT_IP'], $_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }

    #[Test]
    public function itResolvesIpFromVariousServerVariables(): void
    {
        $resolver = new ServerIpResolver();

        // Remote Addr
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $this->assertSame('192.168.1.1', $resolver->getIp());

        // Proxy overrides Remote Addr
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 10.0.0.2';
        $this->assertSame('10.0.0.1', $resolver->getIp());

        // Cloudflare overrides everything
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '1.1.1.1';
        $this->assertSame('1.1.1.1', $resolver->getIp());
    }

    #[Test]
    public function itReturnsUnknownIfNoIpIsFound(): void
    {
        $resolver = new ServerIpResolver();
        $this->assertSame('unknown', $resolver->getIp());
    }
}
