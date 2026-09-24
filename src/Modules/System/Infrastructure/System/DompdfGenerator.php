<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\System;

use App\Contracts\System\PdfGeneratorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Override;

final class DompdfGenerator implements PdfGeneratorInterface
{
    #[Override]
    public function generateFromHtml(string $html): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
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
