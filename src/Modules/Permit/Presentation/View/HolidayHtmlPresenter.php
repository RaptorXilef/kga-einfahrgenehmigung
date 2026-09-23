<?php

declare(strict_types=1);

namespace App\Modules\Permit\Presentation\View;

/**
 * Presenter für die Aufbereitung von Feiertagen und Öffnungszeiten.
 * Strikt BEM-konform. Nutzt ausschließlich SCSS Utility-Klassen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class HolidayHtmlPresenter
{
    /**
     * Verwandelt das Rohdaten-Array der Öffnungszeiten in das benötigte HTML-Format.
     */
    public static function formatOpeningHours(array $blocks): string
    {
        $result = [];
        $isMulti = \count($blocks) > 1;

        foreach ($blocks as $block) {
            $hoursHtml = \implode(' &nbsp;|&nbsp; ', \array_map(function (string $text): string {
                $parts = \explode(':', $text, 2);
                if (\count($parts) === 2) {
                    return '<span class="u-text-nowrap"><strong class="u-font-bold">' . $parts[0] . ':</strong>' . $parts[1] . '</span>';
                }

                return '<span class="u-text-nowrap">' . $text . '</span>';
            }, $block['hours_text']));

            if ($isMulti) {
                $result[] = '<div class="u-margin-block-end-xs"><span class="u-color-primary">' . $block['from'] . ' - ' . $block['to'] . ':</span><br>' . $hoursHtml . '</div>';
            } else {
                $result[] = '<div>' . $hoursHtml . '</div>';
            }
        }

        return \implode('', $result);
    }

    /**
     * Formatiert die Feiertags-Daten in einen HTML-Warnhinweis.
     */
    public static function formatHolidayNotice(array $holidays): string
    {
        if ($holidays === []) {
            return '';
        }

        return '🚫 An folgenden Feier- und Ruhetagen ist die Einfahrt untersagt:<br>' . \implode(', ', $holidays) . '.';
    }
}
