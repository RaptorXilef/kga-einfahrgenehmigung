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
use App\Contracts\System\QrCodeGeneratorInterface;
use App\Modules\Permit\Application\Services\HolidayService;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeHandler;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeQuery;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\PermitReadDto;
use App\Modules\Permit\Presentation\View\HolidayHtmlPresenter;
use App\Modules\Permit\Presentation\View\PermitA4Presenter;
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
        private QrCodeGeneratorInterface $qrCodeGenerator,
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
        $checkQrBase64 = $this->qrCodeGenerator->generateDataUri($checkUrl, 160, 0);

        $openingHtml = HolidayHtmlPresenter::formatOpeningHours(
            $this->holidayService->getOpeningHoursDataForDateRange($permit->validFrom, $permit->validUntil),
        );
        $holidayNoticeHtml = HolidayHtmlPresenter::formatHolidayNotice(
            $this->holidayService->getHolidaysInRange($permit->validFrom, $permit->validUntil),
        );

        $docDto = PermitA4Presenter::createViewDto(
            fullIdentifier: $permit->code,
            templateKey: $permit->templateKey,
            jahresFarbe: $this->config->getString('jahresFarbe'),
            vereinsName: $this->config->getString('vereins_name'),
            checkQrBase64: $checkQrBase64,
            openingHtml: $openingHtml,
            holidayNoticeHtml: $holidayNoticeHtml,
            name: $permit->ownerName,
            validFrom: $permit->validFrom,
            validUntil: $permit->validUntil,
            kennzeichen: $permit->licensePlate,
            firma: $permit->company ?? '',
            parzelle: $permit->plotNumber,
            zweck: $permit->purpose,
            terminkalenderUrl: $this->config->getString('terminkalender_url'),
            erstelltFormatted: $permit->createdAtFormatted,
            baseUrl: $safeBaseUrl,
        );

        $a4Html = $this->renderer->render('emails/permit_a4_document', ['docDto' => $docDto]);
        $pdfBinary = $this->pdfGenerator->generateFromHtml($a4Html);

        return new PdfStreamResponse($pdfBinary, "Genehmigung_{$permit->code}.pdf");
    }
}
