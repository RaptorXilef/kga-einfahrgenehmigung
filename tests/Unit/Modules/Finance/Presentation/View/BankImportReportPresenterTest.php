<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Finance\Presentation\View;

use App\Modules\Finance\Application\UseCases\ProcessBankImport\BankImportResultDto;
use App\Modules\Finance\Presentation\View\BankImportReportPresenter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BankImportReportPresenter::class)]
final class BankImportReportPresenterTest extends TestCase
{
    #[Test]
    public function itFormatsTheReportCorrectlyWithSimpleAndNestedLists(): void
    {
        $dto = new BankImportResultDto(
            success: true,
            message: 'OK',
            successCount: 1,
            skippedCount: 1,
            errorCount: 1,
            successDetails: ['EFG-123 (Max)'], // Simple numeric array
            skippedDetails: ['Bereits verbucht' => ['EFG-456 (Anna)']], // Nested associative array
            errorDetails: ['Zu geringer Betrag' => ['EFG-789 (Tom)']],
            collectiveTransfers: [],
        );

        $html = BankImportReportPresenter::formatFlashReport($dto, 'https://kga.de/');

        $this->assertStringContainsString('Bank-Abgleich beendet: <strong>1</strong> Permits freigeschaltet, 1 übersprungen, 1 fehlerhaft.', $html);

        // Success List (Simple)
        $this->assertStringContainsString('<strong>Freigeschaltet:</strong><ul class="u-margin-block-xs u-padding-inline-start-m"><li>EFG-123 (Max)</li></ul>', $html);

        // Skipped List (Nested)
        $this->assertStringContainsString('<strong class="u-font-bold"><em>Bereits verbucht</em></strong>:', $html);
        $this->assertStringContainsString('<li>EFG-456 (Anna)</li>', $html);

        // Error List (Nested)
        $this->assertStringContainsString('<strong class="u-font-bold"><em>Zu geringer Betrag</em></strong>:', $html);
        $this->assertStringContainsString('<li>EFG-789 (Tom)</li>', $html);
    }
}
