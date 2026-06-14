<?php

declare(strict_types=1);

namespace App\Application\View;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/View/HolidayHtmlPresenter.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final class HolidayHtmlPresenter
{
    /**
     * TODO DOCBLOCK
     * Verwandelt das Rohdaten-Array der Öffnungszeiten in das benötigte HTML-Format.
     */
    public static function formatOpeningHours(array $blocks): string
    {
        $result  = [];
        $isMulti = \count($blocks) > 1;

        foreach ($blocks as $block) {
            $hoursHtml = \implode(' &nbsp;|&nbsp; ', \array_map(function (string $text): string {
                $parts = \explode(':', $text, 2);
                if (\count($parts) === 2) {
                    return '<span style="white-space: nowrap;"><strong>' . $parts[0] . ':</strong>' . $parts[1] . '</span>';
                }

                return '<span style="white-space: nowrap;">' . $text . '</span>';
            }, $block['hours_text']));

            if ($isMulti) {
                $result[] = '<div style="margin-bottom: 6px;"><span style="color: var(--primary-color);">' . $block['from'] . ' - ' . $block['to'] . ':</span><br>' . $hoursHtml . '</div>';
            } else {
                $result[] = '<div>' . $hoursHtml . '</div>';
            }
        }

        return \implode('', $result);
    }

    /**
     * TODO DOCBLOCK
     * Formatiert die Feiertags-Daten in einen HTML-Warnhinweis.
     */
    public static function formatHolidayNotice(array $holidays): string
    {
        if (empty($holidays)) {
            return '';
        }

        return '🚫 An folgenden Feier- und Ruhetagen ist die Einfahrt untersagt:<br>' . \implode(', ', $holidays) . '.';
    }
}
