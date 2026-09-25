<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\System;

use App\Contracts\System\QrCodeGeneratorInterface;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Override;

/**
 * Infrastruktur-Adapter für die QR-Code-Erzeugung mittels Endroid\QrCode.
 */
final readonly class EndroidQrCodeGenerator implements QrCodeGeneratorInterface
{
    #[Override]
    public function generatePng(string $data, int $size = 200, int $margin = 10): array
    {
        $qrCode = new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: $size,
            margin: $margin,
        );

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return [
            'content' => $result->getString(),
            'mimeType' => $result->getMimeType(),
        ];
    }

    #[Override]
    public function generateDataUri(string $data, int $size = 160, int $margin = 0): string
    {
        $qrCode = new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: $size,
            margin: $margin,
        );

        $writer = new PngWriter();

        return $writer->write($qrCode)->getDataUri();
    }
}
