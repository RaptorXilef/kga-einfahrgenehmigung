<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Permit\Presentation\View;

use App\Modules\Permit\Presentation\View\PermitA4Presenter;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PermitA4Presenter::class)]
final class PermitA4PresenterTest extends TestCase
{
    #[Test]
    public function itCalculatesQuartersAndFormatsPermanentPermits(): void
    {
        $dto = PermitA4Presenter::createViewDto(
            'T-123',
            'perm_6',
            '#2ecc71',
            'KGA',
            'qr-base64',
            'Opening',
            'Holiday',
            'Max',
            new DateTimeImmutable('2026-04-10'), // Quartal 2
            new DateTimeImmutable('2026-09-10'), // Quartal 3
            'B-XX 123',
            'Firma',
            '42',
            'Bau',
            'url',
            '01.01.2026',
            'https://base.de/',
        );

        $this->assertTrue($dto->isPermanent);
        $this->assertSame('#3498db', $dto->headerColor); // Override for permanent
        $this->assertSame('DAUER&shy;EINFAHRT&shy;GENEHMIGUNG', $dto->titleHtml);

        // Quartal 2 und 3 müssen aktiv sein, 1 und 4 inaktiv
        $this->assertSame('c-quarter--inactive', $dto->quarters[0]['cssClass']);
        $this->assertSame('c-quarter--active', $dto->quarters[1]['cssClass']);
        $this->assertSame('c-quarter--active', $dto->quarters[2]['cssClass']);
        $this->assertSame('c-quarter--inactive', $dto->quarters[3]['cssClass']);
    }

    #[Test]
    public function itFormatsStandardPermitsCorrectly(): void
    {
        $dto = PermitA4Presenter::createViewDto(
            'T-123',
            'std_7',
            '#2ecc71',
            'KGA',
            'qr-base64',
            '',
            '',
            'Max',
            new DateTimeImmutable('2026-04-10'),
            new DateTimeImmutable('2026-04-15'),
            'B-XX 123',
            '',
            '42',
            'Bau',
            'url',
            '01.01.2026',
            'https://base.de/',
        );

        $this->assertFalse($dto->isPermanent);
        $this->assertSame('#2ecc71', $dto->headerColor);
        $this->assertSame('AUSNAHME&shy;GENEHMIGUNG', $dto->titleHtml);
        $this->assertSame('Siehe Aushang', $dto->openingTextHtml); // Fallback if empty
    }

    #[Test]
    public function itThrowsExceptionIfBaseUrlIsEmpty(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sicherheits-Abbruch: "base_url" fehlt für die PDF/E-Mail-Generierung.');

        PermitA4Presenter::createViewDto(
            'T',
            'std',
            '',
            '',
            '',
            '',
            '',
            '',
            new DateTimeImmutable(),
            new DateTimeImmutable(),
            '',
            '',
            '',
            '',
            '',
            '',
            '   ', // Leere Base URL
        );
    }
}
