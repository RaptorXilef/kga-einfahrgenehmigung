<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\PrintPermit;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\EmptyResponse;
use App\Application\Response\PdfStreamResponse;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\AuditLoggerInterface;
use App\Contracts\System\PdfGeneratorInterface;
use App\Modules\Permit\Application\Services\HolidayService;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeHandler;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeQuery;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\PermitReadDto;
use App\Modules\Permit\Presentation\View\HolidayHtmlPresenter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Override;

#[Route('GET', '/admin_print')]
#[RequiresAuth]
final readonly class AdminPrintAction implements ViewActionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private ConfigInterface $config,
        private HolidayService $holidayService,
        private GetPermitByCodeHandler $getPermitByCodeHandler,
        private PdfGeneratorInterface $pdfGenerator,
        private TemplateRenderer $renderer,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = PrintPermitRequest::fromArray($request->get);
        } catch (ValidationException) {
            return new EmptyResponse(400);
        }

        $code = $dto->code;
        $permit = $this->getPermitByCodeHandler->handle(new GetPermitByCodeQuery($code));

        if (!$permit instanceof PermitReadDto) {
            return new EmptyResponse(404);
        }

        $this->auditLogger->log('PERMIT_PRINT', "Druck-PDF für Genehmigung '{$code}' generiert.");

        $safeBaseUrl = \rtrim($this->config->getBaseUrl(), '/') . '/';
        $checkUrl = $safeBaseUrl . 'check?code=' . $permit->code;

        $qrCode = new QrCode(
            data: $checkUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: 160,
            margin: 0,
        );
        $writer = new PngWriter();
        $checkQrBase64 = $writer->write($qrCode)->getDataUri();

        $pdfData = [
            'bis_formatted' => $permit->validUntilFormatted,
            'checkUrl' => $checkUrl,
            'checkQrBase64' => $checkQrBase64,
            'erstellt' => $permit->createdAtFormatted,
            'firma' => $permit->company ?? '',
            'fullIdentifier' => $permit->code,
            'holidayNotice' => HolidayHtmlPresenter::formatHolidayNotice(
                $this->holidayService->getHolidaysInRange($permit->validFrom, $permit->validUntil),
            ),
            'jahresFarbe' => $this->config->getString('jahresFarbe'),
            'kennzeichen' => $permit->licensePlate,
            'name' => $permit->ownerName,
            'opening_html' => HolidayHtmlPresenter::formatOpeningHours(
                $this->holidayService->getOpeningHoursDataForDateRange($permit->validFrom, $permit->validUntil),
            ),
            'parzelle' => $permit->plotNumber,
            'settings' => ['base_url' => $safeBaseUrl],
            'template_key' => $permit->templateKey,
            'terminkalenderUrl' => $this->config->getString('terminkalender_url'),
            'vereinsName' => $this->config->getString('vereins_name'),
            'von_formatted' => $permit->validFromFormatted,
            'zweck' => $permit->purpose,
        ];

        $a4Html = $this->renderer->render('emails/permit_a4_document', $pdfData);
        $pdfBinary = $this->pdfGenerator->generateFromHtml($a4Html);

        return new PdfStreamResponse($pdfBinary, "Genehmigung_{$permit->code}.pdf");
    }
}
