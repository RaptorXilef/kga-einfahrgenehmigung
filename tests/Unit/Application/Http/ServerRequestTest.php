<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Http;

use App\Application\Http\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerRequest::class)]
final class ServerRequestTest extends TestCase
{
    #[Test]
    public function itCorrectlyResolvesHttpMethodsAndPaths(): void
    {
        $server = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/test?foo=bar',
            'CONTENT_TYPE' => 'application/json',
        ];
        $request = new ServerRequest(server: $server);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/test?foo=bar', $request->getPath());
        $this->assertSame('application/json', $request->getContentType());
    }

    #[Test]
    #[DataProvider('ipProvider')]
    public function itResolvesIpAddressConsideringProxyHeaders(array $serverParams, string $expectedIp): void
    {
        $request = new ServerRequest(server: $serverParams);
        $this->assertSame($expectedIp, $request->getIp());
    }

    public static function ipProvider(): array
    {
        return [
            'standard remote addr' => [['REMOTE_ADDR' => '192.168.1.5'], '192.168.1.5'],
            'cloudflare proxy' => [['HTTP_CF_CONNECTING_IP' => '10.0.0.1', 'REMOTE_ADDR' => '192.168.1.5'], '10.0.0.1'],
            'x forwarded for with multiple ips' => [['HTTP_X_FORWARDED_FOR' => '203.0.113.195, 70.41.3.18, 150.172.238.178'], '203.0.113.195'],
            // Die folgenden Zeilen töten die isset/is_string Mutanten von Infection:
            'x forwarded with spaces' => [['HTTP_X_FORWARDED_FOR' => '  10.0.0.5  , 1.2.3.4'], '10.0.0.5'],
            // Non-String Value (Soll ignoriert werden)
            'array instead of string' => [['HTTP_CF_CONNECTING_IP' => ['10.0.0.1'], 'REMOTE_ADDR' => '192.168.1.5'], '192.168.1.5'],
            // KILLT MUTANTE 1: Wenn eine Zahl anstelle eines Strings übergeben wird
            'integer instead of string' => [['HTTP_CF_CONNECTING_IP' => 12345, 'REMOTE_ADDR' => '192.168.1.5'], '192.168.1.5'],
            // Empty String Header (Soll ignoriert werden)
            'empty string header' => [['HTTP_CF_CONNECTING_IP' => '', 'REMOTE_ADDR' => '192.168.1.5'], '192.168.1.5'],
            'unknown fallback' => [[], 'unknown'],
        ];
    }

    #[Test]
    public function itAllowsImmutablyUpdatingInputData(): void
    {
        $original = new ServerRequest(get: ['a' => 1]);
        $modified = $original->withInput(['b' => 2]);

        $this->assertEmpty($original->input);
        $this->assertSame(['b' => 2], $modified->input);
        $this->assertNotSame($original, $modified);
    }
}
