<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetVerifiedRequest;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Modules\Permit\Application\Services\HolidayService;
use App\Modules\Permit\Presentation\View\HolidayHtmlPresenter;
use DateTimeImmutable;
use Exception;

#[Route('GET', '/checkout')]
final readonly class CheckoutAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private HolidayService $holidayService,
        private GetVerifiedRequestHandler $getVerifiedHandler,
        private TemplateRenderer $renderer,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = CheckoutRequest::fromArray($request->get);
        } catch (Exception) {
            return new RedirectResponse('/');
        }

        $token = $dto->token;
        $tempData = $this->getVerifiedHandler->handle(new GetVerifiedRequestQuery($token));

        if ($tempData === null) {
            return new RedirectResponse('/');
        }

        $dtVon = new DateTimeImmutable($tempData['datum_von'] ?? 'now');
        $dtBis = new DateTimeImmutable($tempData['datum_bis'] ?? 'now');

        $vehicleTypes = $this->config->get('vehicle_types', []);
        $vKey = $tempData['typ'] ?? '';
        $typLabel = $vehicleTypes[$vKey]['label'] ?? $vKey;

        $purposes = $this->config->get('purposes', []);
        $zKey = $tempData['zweck'] ?? '';
        $zweckLabel = $purposes[$zKey] ?? $zKey;

        $paypalConfig = $this->config->get('paypal', []);
        $isPayPalEnabled = ($paypalConfig['enabled'] ?? false) === true;

        $preisRaw = (float) ($tempData['preis'] ?? 0);

        $viewDto = new CheckoutSummaryViewDto(
            token: $token,
            isPayPalEnabled: $isPayPalEnabled,
            name: (string) ($tempData['name'] ?? ''),
            email: (string) ($tempData['email'] ?? ''),
            parzelle: (string) ($tempData['parzelle'] ?? ''),
            typLabel: $typLabel,
            kennzeichen: (string) ($tempData['kennzeichen'] ?? ''),
            firma: (string) ($tempData['firma'] ?? ''),
            zweckLabel: $zweckLabel,
            datumVon: $dtVon->format('d.m.Y'),
            datumBis: $dtBis->format('d.m.Y'),
            preisRaw: $preisRaw,
            preisFormatted: \number_format($preisRaw, 2, ',', '.') . ' €',
            openingHoursHtml: HolidayHtmlPresenter::formatOpeningHours(
                $this->holidayService->getOpeningHoursDataForDateRange($dtVon, $dtBis),
            ),
            holidayNoticeHtml: HolidayHtmlPresenter::formatHolidayNotice(
                $this->holidayService->getHolidaysInRange($dtVon, $dtBis),
            ),
        );

        $html = $this->renderer->render('frontend/checkout_summary', [
            'viewDto' => $viewDto,
        ]);

        return new HtmlResponse($html);
    }
}
