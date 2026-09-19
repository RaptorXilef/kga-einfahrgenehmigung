<?php

declare(strict_types=1);

namespace App\Application\Actions\Api\Shared;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\EmptyResponse;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Exception;

/**
 * Generiert QR-Codes lokal und DSGVO-konform.
 * Ersetzt den unsicheren Aufruf an externe APIs (qrserver.com).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('GET', '/api/qr')]
final readonly class QrCodeRenderAction implements ActionInterface
{
    public function execute(ServerRequest $request): mixed
    {
        $data = $request->get['data'] ?? '';
        $size = (int) ($request->get['size'] ?? 200);
        $margin = (int) ($request->get['margin'] ?? 10);

        if ($data === '' || $size < 50 || $size > 1000) {
            return new EmptyResponse(400);
        }

        try {
            // Klassische Instanziierung statt statischem Builder (Behebt IDE-Warnungen)
            $qrCode = new QrCode(
                data: (string) $data,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Low,
                size: $size,
                margin: $margin,
            );

            $writer = new PngWriter();
            $result = $writer->write($qrCode);

            // Sende das Bild direkt als binären PNG-Stream an den Browser / Mail-Client
            if (!\headers_sent()) {
                \header('Content-Type: ' . $result->getMimeType());
                \header('Cache-Control: public, max-age=31536000'); // 1 Jahr cachen
            }

            echo $result->getString();
            exit;

        } catch (Exception $e) {
            \error_log('QR-Code Generierung fehlgeschlagen: ' . $e->getMessage());

            return new EmptyResponse(500);
        }
    }
}
