<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\QrCode;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\BinaryResponse;
use App\Application\Response\EmptyResponse;
use App\Contracts\System\QrCodeGeneratorInterface;
use Override;
use Throwable;

#[Route('GET', '/api/qr.png')]
final readonly class QrCodeRenderAction implements ActionInterface
{
    public function __construct(
        private QrCodeGeneratorInterface $qrCodeGenerator,
    ) {
    }

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
            $result = $this->qrCodeGenerator->generatePng((string) $data, $size, $margin);

            return new BinaryResponse(
                content: $result['content'],
                contentType: $result['mimeType'],
                statusCode: 200,
                headers: ['Cache-Control' => 'public, max-age=31536000'],
            );
        } catch (Throwable $e) {
            \error_log('QR-Code Generierung fehlgeschlagen: ' . $e->getMessage());

            return new EmptyResponse(500);
        }
    }
}
