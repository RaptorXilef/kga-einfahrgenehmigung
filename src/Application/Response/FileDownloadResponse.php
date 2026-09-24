<?php

declare(strict_types=1);

namespace App\Application\Response;

use App\Application\Contracts\ResponseInterface;
use Override;

/**
 * Erzwingt den Datei-Download eines generierten Inhalts im Browser.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class FileDownloadResponse implements ResponseInterface
{
    public function __construct(public string $content, public string $filename, public string $contentType)
    {
    }

    #[Override]
    public function send(): void
    {
        \header('Content-Type: ' . $this->contentType);
        \header('Content-Disposition: attachment; filename="' . $this->filename . '"');
        echo $this->content;
        exit;
    }
}
