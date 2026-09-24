<?php

declare(strict_types=1);

namespace App\Application\Response;

use App\Application\Contracts\ResponseInterface;
use Override;

/**
 * Streamt ein binäres PDF-Dokument direkt in den Browser.
 * Nutzt 'inline', damit es im integrierten PDF-Viewer angezeigt statt sofort heruntergeladen wird.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PdfStreamResponse implements ResponseInterface
{
    public function __construct(
        public string $content,
        public string $filename,
    ) {
    }

    #[Override]
    public function send(): void
    {
        \http_response_code(200);
        \header('Content-Type: application/pdf');
        // 'inline' sorgt dafür, dass der Browser es anzeigt (Druck-Ansicht), 'attachment' würde einen Download erzwingen
        \header('Content-Disposition: inline; filename="' . $this->filename . '"');
        \header('Cache-Control: private, max-age=0, must-revalidate');
        \header('Pragma: public');

        echo $this->content;
        exit;
    }
}
