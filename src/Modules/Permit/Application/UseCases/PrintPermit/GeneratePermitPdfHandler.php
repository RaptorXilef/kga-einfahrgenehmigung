<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\PrintPermit;

use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\PdfGeneratorInterface;
use App\Contracts\System\QrCodeGeneratorInterface;
use App\Modules\Permit\Application\Services\HolidayService;
use App\Modules\Permit\Presentation\View\HolidayHtmlPresenter;
use App\Modules\Permit\Presentation\View\PermitA4Presenter;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Query\QueryInterface;
use Override;

/**
 * Orchestriert die QR-Code-Erzeugung, Feiertagsberechnung, das View-DTO-Mapping
 * und das Rendern des binären A4-Genehmigungs-PDFs.
 *
 * @implements QueryHandlerInterface<GeneratePermitPdfQuery, string>
 */
final readonly class GeneratePermitPdfHandler implements QueryHandlerInterface
{
    public function __construct(
        private ConfigInterface $config,
        private HolidayService $holidayService,
        private PdfGeneratorInterface $pdfGenerator,
        private QrCodeGeneratorInterface $qrCodeGenerator,
        private TemplateRenderer $renderer,
    ) {
    }

    /**
     * @param GeneratePermitPdfQuery $query
     */
    #[Override]
    public function handle(QueryInterface $query): string
    {
        $permit = $query->permit;

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

        return $this->pdfGenerator->generateFromHtml($a4Html);
    }
}
