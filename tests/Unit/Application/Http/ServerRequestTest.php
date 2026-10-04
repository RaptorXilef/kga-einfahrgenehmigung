<?php

declare(strict_types=1);

use App\Application\Http\ServerRequest;

\covers(ServerRequest::class);

\test('it correctly resolves http methods and paths', function (): void {
    $server = [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/api/test?foo=bar',
        'CONTENT_TYPE' => 'application/json',
    ];

    $request = new ServerRequest(server: $server);

    \expect($request->getMethod())->toBe('POST')
        ->and($request->getPath())->toBe('/api/test?foo=bar')
        ->and($request->getContentType())->toBe('application/json');
});

\test('it resolves ip address considering proxy headers', function (array $serverParams, string $expectedIp): void {
    $request = new ServerRequest(server: $serverParams);

    \expect($request->getIp())->toBe($expectedIp);
})->with([
    'standard remote addr' => [
        ['REMOTE_ADDR' => '192.168.1.5'],
        '192.168.1.5',
    ],
    'cloudflare proxy' => [
        ['HTTP_CF_CONNECTING_IP' => '10.0.0.1', 'REMOTE_ADDR' => '192.168.1.5'],
        '10.0.0.1', // CF header takes precedence
    ],
    'x forwarded for with multiple ips' => [
        ['HTTP_X_FORWARDED_FOR' => '203.0.113.195, 70.41.3.18, 150.172.238.178'],
        '203.0.113.195', // Should extract the first IP
    ],
    'unknown fallback' => [
        [],
        'unknown',
    ],
]);

\test('it allows immutably updating input data', function (): void {
    $original = new ServerRequest(get: ['a' => 1]);
    $modified = $original->withInput(['b' => 2]);

    \expect($original->input)->toBe([])
        ->and($modified->input)->toBe(['b' => 2])
        ->and($original)->not->toBe($modified);
});
