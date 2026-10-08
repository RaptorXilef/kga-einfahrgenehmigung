<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Http;

use App\Application\Http\ServerRequest;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerRequest::class)]
final class ServerRequestTest extends TestCase
{
    #[Test]
    public function itCorrectlyResolvesHttpMethodsPathsAndHeaders(): void
    {
        $server = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/test?foo=bar',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => 'secret-token-123',
        ];
        $request = new ServerRequest(server: $server);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/test?foo=bar', $request->getPath());
        $this->assertSame('application/json', $request->getContentType());
        $this->assertSame('secret-token-123', $request->getHeader('x-csrf-token'));
        $this->assertSame('', $request->getHeader('X-Non-Existent'));
    }

    #[Test]
    public function itFallsBackToSafeDefaultsWhenServerValuesAreMissingOrNonStrings(): void
    {
        $emptyRequest = new ServerRequest();
        $this->assertSame('GET', $emptyRequest->getMethod());
        $this->assertSame('', $emptyRequest->getPath());
        $this->assertSame('', $emptyRequest->getContentType());

        $corruptRequest = new ServerRequest(server: [
            'REQUEST_METHOD' => ['INVALID'],
            'REQUEST_URI' => 12345,
            'CONTENT_TYPE' => false,
            'HTTP_X_CUSTOM' => ['not-a-string'],
        ]);

        $this->assertSame('GET', $corruptRequest->getMethod());
        $this->assertSame('', $corruptRequest->getPath());
        $this->assertSame('', $corruptRequest->getContentType());
        $this->assertSame('', $corruptRequest->getHeader('X-Custom'));
    }

    #[Test]
    #[DataProvider('ipProvider')]
    public function itResolvesIpAddressConsideringProxyHeaders(array $serverParams, string $expectedIp): void
    {
        $request = new ServerRequest(server: $serverParams);
        $this->assertSame($expectedIp, $request->getIp());
    }

    public static function ipProvider(): Iterator
    {
        yield 'standard remote addr' => [['REMOTE_ADDR' => '192.168.1.5'], '192.168.1.5'];
        yield 'cloudflare proxy' => [['HTTP_CF_CONNECTING_IP' => '10.0.0.1', 'REMOTE_ADDR' => '192.168.1.5'], '10.0.0.1'];
        yield 'x forwarded for with multiple ips' => [['HTTP_X_FORWARDED_FOR' => '203.0.113.195, 70.41.3.18, 150.172.238.178'], '203.0.113.195'];
        // Die folgenden Zeilen töten die isset/is_string Mutanten von Infection:
        yield 'x forwarded with spaces' => [['HTTP_X_FORWARDED_FOR' => '  10.0.0.5  , 1.2.3.4'], '10.0.0.5'];
        // Non-String Value (Soll ignoriert werden)
        yield 'array instead of string' => [['HTTP_CF_CONNECTING_IP' => ['10.0.0.1'], 'REMOTE_ADDR' => '192.168.1.5'], '192.168.1.5'];
        // KILLT MUTANTE 1: Wenn eine Zahl anstelle eines Strings übergeben wird
        yield 'integer instead of string' => [['HTTP_CF_CONNECTING_IP' => 12345, 'REMOTE_ADDR' => '192.168.1.5'], '192.168.1.5'];
        // Empty String Header (Soll ignoriert werden)
        yield 'empty string header' => [['HTTP_CF_CONNECTING_IP' => '', 'REMOTE_ADDR' => '192.168.1.5'], '192.168.1.5'];
        yield 'unknown fallback' => [[], 'unknown'];
    }

    #[Test]
    public function itAllowsImmutablyUpdatingInputDataWhilePreservingAllOtherBags(): void
    {
        $original = new ServerRequest(
            get: ['g' => 1],
            post: ['p' => 2],
            files: ['f' => 3],
            server: ['s' => 4],
            input: ['i' => 5],
            cookie: ['c' => 6],
        );
        $modified = $original->withInput(['b' => 2]);

        $this->assertSame(['i' => 5], $original->input);
        $this->assertSame(['b' => 2], $modified->input);
        $this->assertSame(['g' => 1], $modified->get);
        $this->assertSame(['p' => 2], $modified->post);
        $this->assertSame(['f' => 3], $modified->files);
        $this->assertSame(['s' => 4], $modified->server);
        $this->assertSame(['c' => 6], $modified->cookie);
        $this->assertNotSame($original, $modified);
    }
}
