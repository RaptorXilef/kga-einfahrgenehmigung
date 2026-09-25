<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\PrintPermit;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\PdfStreamResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\PdfGeneratorInterface;
use App\Modules\Permit\Application\Services\HolidayService;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeHandler;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeQuery;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\PermitReadDto;
use App\Modules\Permit\Presentation\View\HolidayHtmlPresenter;
use App\Modules\Permit\Presentation\View\PermitA4Presenter;
use App\SharedKernel\Application\Security\Sanitizer;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Override;

#[Route('GET', '/history_print')]
#[Route('POST', '/history_print')]
final readonly class HistoryPrintAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private HolidayService $holidayService,
        private GetPermitByCodeHandler $getPermitByCodeHandler,
        private SessionManager $sessionManager,
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
            return new RedirectResponse('history');
        }

        $code = $dto->code;
        $emailInSession = (string) $this->sessionManager->getHistoryEmail();

        $permit = $this->getPermitByCodeHandler->handle(new GetPermitByCodeQuery($code));

        if ($permit instanceof PermitReadDto && Sanitizer::normalizeEmail($permit->ownerEmail) === Sanitizer::normalizeEmail($emailInSession)) {
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

        return new RedirectResponse('history');
    }
}
