<?php

declare(strict_types=1);

namespace App\Contracts\System;

/**
 * Interface zur Generierung von PDF-Dokumenten aus HTML.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface PdfGeneratorInterface
{
    /**
     * Wandelt einen HTML-String in einen binären PDF-String um.
     *
     * @param string $html Das Quell-HTML
     * @return string Die binären PDF-Daten (für Dateisystem oder E-Mail-Anhang)
     */
    public function generateFromHtml(string $html): string;
}
