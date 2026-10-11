<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Middleware;

use App\Application\Contracts\MiddlewareInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Response\HtmlResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MiddlewarePipeline::class)]
final class MiddlewarePipelineTest extends TestCase
{
    #[Test]
    public function itExecutesMiddlewaresInCorrectOnionOrder(): void
    {
        $pipeline = new MiddlewarePipeline();
        $executionLog = [];

        // Das & steht NUR in der Methoden-Signatur, nicht beim Aufruf!
        $middleware1 = new class($executionLog) implements MiddlewareInterface {
            public function __construct(private array &$log)
            {
            }

            public function process(ServerRequest $request, callable $next): ResponseInterface
            {
                $this->log[] = 'M1_IN';
                $response = $next($request);
                $this->log[] = 'M1_OUT';

                return $response;
            }
        };

        $middleware2 = new class($executionLog) implements MiddlewareInterface {
            public function __construct(private array &$log)
            {
            }

            public function process(ServerRequest $request, callable $next): ResponseInterface
            {
                $this->log[] = 'M2_IN';
                $response = $next($request);
                $this->log[] = 'M2_OUT';

                return $response;
            }
        };

        $pipeline->add($middleware1)->add($middleware2);

        $coreAction = function (ServerRequest $req) use (&$executionLog): ResponseInterface {
            $executionLog[] = 'CORE_ACTION';

            return new HtmlResponse('OK');
        };

        $response = $pipeline->process(new ServerRequest(), $coreAction);

        $this->assertInstanceOf(HtmlResponse::class, $response);
        $this->assertSame([
            'M1_IN',
            'M2_IN',
            'CORE_ACTION',
            'M2_OUT',
            'M1_OUT',
        ], $executionLog);
    }
}
