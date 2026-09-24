<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\QrCode;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\BinaryResponse;
use App\Application\Response\EmptyResponse;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Exception;
use Override;

#[Route('GET', '/api/qr.png')]
final readonly class QrCodeRenderAction implements ActionInterface
{
    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $data = $request->get['data'] ?? '';
        $size = (int) ($request->get['size'] ?? 200);
        $margin = (int) ($request->get['margin'] ?? 10);

        if ($data === '' || $size < 50 || $size > 1000) {
            return new EmptyResponse(400);
        }

        try {
            $qrCode = new QrCode(
                data: (string) $data,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::Low,
                size: $size,
                margin: $margin,
            );

            $writer = new PngWriter();
            $result = $writer->write($qrCode);

            return new BinaryResponse(
                content: $result->getString(),
                contentType: $result->getMimeType(),
                statusCode: 200,
                headers: ['Cache-Control' => 'public, max-age=31536000'],
            );
        } catch (Exception $e) {
            \error_log('QR-Code Generierung fehlgeschlagen: ' . $e->getMessage());

            return new EmptyResponse(500);
        }
    }
}
