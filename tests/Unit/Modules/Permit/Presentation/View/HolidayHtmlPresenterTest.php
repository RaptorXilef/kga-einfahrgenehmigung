<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Permit\Presentation\View;

use App\Modules\Permit\Presentation\View\HolidayHtmlPresenter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HolidayHtmlPresenter::class)]
final class HolidayHtmlPresenterTest extends TestCase
{
    #[Test]
    public function itFormatsOpeningHoursForSingleAndMultipleBlocks(): void
    {
        $singleBlock = [
            ['from' => '01.10.2026', 'to' => '05.10.2026', 'hours_text' => ['Mo, Di: 07:00 - 20:00', 'So: Keine Einfahrt']],
        ];

        $htmlSingle = HolidayHtmlPresenter::formatOpeningHours($singleBlock);
        // Single block renders without the date range header
        $this->assertStringNotContainsString('01.10.2026 - 05.10.2026', $htmlSingle);
        $this->assertStringContainsString('<span class="u-text-nowrap"><strong class="u-font-bold">Mo, Di:</strong> 07:00 - 20:00</span>', $htmlSingle);
        $this->assertStringContainsString('<span class="u-text-nowrap"><strong class="u-font-bold">So:</strong> Keine Einfahrt</span>', $htmlSingle);

        $multiBlock = [
            ['from' => '01.10.', 'to' => '05.10.', 'hours_text' => ['Mo: 07:00']],
            ['from' => '06.10.', 'to' => '10.10.', 'hours_text' => ['Di: 08:00']],
        ];

        $htmlMulti = HolidayHtmlPresenter::formatOpeningHours($multiBlock);
        // Multi blocks render WITH date range headers
        $this->assertStringContainsString('<span class="u-color-primary">01.10. - 05.10.:</span>', $htmlMulti);
        $this->assertStringContainsString('<span class="u-color-primary">06.10. - 10.10.:</span>', $htmlMulti);
    }

    #[Test]
    public function itFormatsHolidayNoticeCorrectly(): void
    {
        $this->assertSame('', HolidayHtmlPresenter::formatHolidayNotice([]));

        $html = HolidayHtmlPresenter::formatHolidayNotice(['03.10.2026', '24.12.2026']);
        $this->assertSame('🚫 An folgenden Feier- und Ruhetagen ist die Einfahrt untersagt:<br>03.10.2026, 24.12.2026.', $html);
    }
}
