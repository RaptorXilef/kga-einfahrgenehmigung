<?php

declare(strict_types=1);

namespace App\Application\Actions\Frontend;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\SimpleCodeRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\PdfStreamResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Application\View\HolidayHtmlPresenter;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\PdfGeneratorInterface;
use App\Core\Entity\Permit;
use App\Core\Security\Sanitizer;
use App\Core\Service\HolidayService;
use App\Core\Service\PermitService;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

#[Route('GET', '/history_print')]
#[Route('POST', '/history_print')]
final readonly class HistoryPrintAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private HolidayService $holidayService,
        private PermitService $permitService,
        private SessionManager $sessionManager,
        private PdfGeneratorInterface $pdfGenerator,
        private TemplateRenderer $renderer,
    ) {
    }

    /**
     * Validiert den Zugriff und rendert die Druckansicht einer spezifischen Genehmigung.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleCodeRequest::fromArray($request->get);
        } catch (ValidationException) {
            return new RedirectResponse('history');
        }

        $code = $dto->code;
        $emailInSession = (string) $this->sessionManager->getHistoryEmail();

        $permit = $this->permitService->resolvePermit($code);

        // Vergleicht die E-Mails via Normalisierung (+ Aliase) für höchste Zuverlässigkeit
        if ($permit instanceof Permit && Sanitizer::normalizeEmail($permit->getOwnerEmail()) === Sanitizer::normalizeEmail($emailInSession)) {

            $safeBaseUrl = \rtrim($this->config->getBaseUrl(), '/') . '/';
            $checkUrl = $safeBaseUrl . 'check?code=' . $permit->code->value;

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
                'bis_formatted' => $permit->getValidUntil()->format('d.m.Y'),
                'checkUrl' => $checkUrl,
                'checkQrBase64' => $checkQrBase64,
                'erstellt' => $permit->getCreatedAt()->format('d.m.Y H:i'),
                'firma' => $permit->getCompany() ?? '',
                'fullIdentifier' => $permit->code->value,
                'holidayNotice' => HolidayHtmlPresenter::formatHolidayNotice(
                    $this->holidayService->getHolidaysInRange($permit->getValidFrom(), $permit->getValidUntil()),
                ),
                'jahresFarbe' => $this->config->get('jahresFarbe'),
                'kennzeichen' => $permit->getLicensePlate(),
                'name' => $permit->getOwnerName(),
                'opening_html' => HolidayHtmlPresenter::formatOpeningHours(
                    $this->holidayService->getOpeningHoursDataForDateRange($permit->getValidFrom(), $permit->getValidUntil()),
                ),
                'parzelle' => $permit->getPlotNumber(),
                'settings' => ['base_url' => $safeBaseUrl],
                'template_key' => $permit->template_key->value,
                'terminkalenderUrl' => $this->config->get('terminkalender_url'),
                'vereinsName' => $this->config->get('vereins_name'),
                'von_formatted' => $permit->getValidFrom()->format('d.m.Y'),
                'zweck' => $permit->getPurpose(),
            ];

            $a4Html = $this->renderer->render('emails/permit_a4_document', $pdfData);
            $pdfBinary = $this->pdfGenerator->generateFromHtml($a4Html);

            return new PdfStreamResponse($pdfBinary, "Genehmigung_{$permit->code->value}.pdf");
        }

        return new RedirectResponse('history');
    }
}
