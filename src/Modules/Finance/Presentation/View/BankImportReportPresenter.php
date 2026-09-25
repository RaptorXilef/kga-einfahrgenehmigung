<?php

declare(strict_types=1);

namespace App\Modules\Finance\Presentation\View;

use App\Modules\Finance\Application\UseCases\ProcessBankImport\BankImportResultDto;

/**
 * Presenter für die HTML-Aufbereitung des Bank-Import-Ergebnisberichts.
 * Befreit die ProcessBankImportAction vollständig von HTML-Generierungslogik.
 */
final class BankImportReportPresenter
{
    public static function formatFlashReport(BankImportResultDto $result, string $baseUrl): string
    {
        $safeBaseUrl = \rtrim($baseUrl, '/') . '/';
        $htmlDetails = [];

        if ($result->successDetails !== []) {
            $htmlDetails[] = '<div class="u-margin-bottom-s"><img src="' . $safeBaseUrl . 'assets/img/icons/success.webp" class="c-icon c-icon--inline" alt="" loading="lazy"> <strong>Freigeschaltet:</strong>' . self::formatList($result->successDetails) . '</div>';
        }
        if ($result->skippedDetails !== []) {
            $htmlDetails[] = '<div class="u-margin-bottom-s"><img src="' . $safeBaseUrl . 'assets/img/icons/skip.webp" class="c-icon c-icon--inline" alt="" loading="lazy"> <strong>Übersprungen:</strong>' . self::formatList($result->skippedDetails) . '</div>';
        }
        if ($result->errorDetails !== []) {
            $htmlDetails[] = '<div class="u-margin-bottom-s"><img src="' . $safeBaseUrl . 'assets/img/icons/warning.webp" class="c-icon c-icon--inline" alt="" loading="lazy"> <strong>Fehlerhaft / Prüfen:</strong>' . self::formatList($result->errorDetails) . '</div>';
        }

        $summary = "<div class=\"u-margin-bottom-m\">Bank-Abgleich beendet: <strong>{$result->successCount}</strong> Permits freigeschaltet, {$result->skippedCount} übersprungen, {$result->errorCount} fehlerhaft.</div>";

        return $summary . \implode('', $htmlDetails);
    }

    /**
     * @param array<int|string, mixed> $categories
     */
    private static function formatList(array $categories): string
    {
        $html = '<ul class="u-margin-block-xs u-padding-inline-start-m">';
        foreach ($categories as $cat => $items) {
            if (\is_numeric($cat)) {
                $html .= '<li>' . \htmlspecialchars((string) $items) . '</li>';
            } else {
                $html .= '<li class="u-margin-block-end-xs"><strong class="u-font-bold"><em>' . \htmlspecialchars($cat) . '</em></strong>:';
                $html .= '<ul class="u-margin-block-start-none u-margin-block-end-xs u-padding-inline-start-m">';
                foreach ((array) $items as $item) {
                    $html .= '<li>' . \htmlspecialchars((string) $item) . '</li>';
                }
                $html .= '</ul></li>';
            }
        }

        return $html . '</ul>';
    }
}
