<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\RequestMagicLink;

use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Modules\Identity\Application\UseCases\RequestMagicLink\HistoryRequestLinkRequest;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HistoryRequestLinkRequest::class)]
final class HistoryRequestLinkRequestTest extends TestCase
{
    #[Test]
    public function itCreatesRequestFromValidServerRequest(): void
    {
        $serverRequest = new ServerRequest(
            post: ['email' => '  paechter@kga.de  '], // FIX: Keine Umlaute für filter_var()!
            server: ['REMOTE_ADDR' => '10.0.0.5'],
        );

        $request = HistoryRequestLinkRequest::fromRequest($serverRequest);

        $this->assertSame('paechter@kga.de', $request->email); // Trimmed
        $this->assertSame('10.0.0.5', $request->ip);
    }

    #[Test]
    #[DataProvider('invalidEmailProvider')]
    public function itThrowsExceptionOnInvalidEmail(array $post): void
    {
        $serverRequest = new ServerRequest(post: $post);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Bitte geben Sie eine gültige E-Mail-Adresse ein.');

        HistoryRequestLinkRequest::fromRequest($serverRequest);
    }

    public static function invalidEmailProvider(): Iterator
    {
        yield 'empty email' => [['email' => '   ']];
        yield 'missing email' => [[]];
        yield 'invalid format' => [['email' => 'not-an-email']];
        yield 'missing at' => [['email' => 'test.kga.de']];
        yield 'umlaut in local part' => [['email' => 'pächter@kga.de']]; // Wird von filter_var abgelehnt
    }
}
