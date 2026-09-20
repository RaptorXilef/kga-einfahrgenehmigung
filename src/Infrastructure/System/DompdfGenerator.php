<?php

declare(strict_types=1);

namespace App\Infrastructure\System;

use App\Contracts\System\PdfGeneratorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PDF-Generierung via Dompdf.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class DompdfGenerator implements PdfGeneratorInterface
{
    public function generateFromHtml(string $html): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true); // Erlaubt Base64 Grafiken und Remote CSS
        $options->set('defaultPaperSize', 'A4');
        $options->set('defaultFont', 'sans-serif');
        $options->set('dpi', 96);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output() ?: '';
    }
}
