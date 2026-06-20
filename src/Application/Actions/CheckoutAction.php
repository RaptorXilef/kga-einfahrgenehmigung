<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleTokenRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\View\HolidayHtmlPresenter;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\HolidayService;
use App\Core\Service\PermitService;

/**
 * Action für die Checkout-Übersicht.
 * Zeigt dem Benutzer vor dem finalen Zahlungsabschluss eine Zusammenfassung
 * der Antragsdaten und die berechneten Einfahrtszeiten.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class CheckoutAction implements ViewActionInterface
{
    public function __construct(
        private HolidayService $holidayService,
        private PermitService $permitService,
        private TemplateRenderer $renderer,
    ) {
    }

    /**
     * TODO DOCBLOCK
     * Haupt-Request-Handler für den Checkout-Prozess.
     *
     * Verifiziert das übergebene Token, validiert die Session-Daten und rendert
     * die Zusammenfassungs-Seite mit Feiertagsberechnung.
     */
    public function execute(ServerRequest $request): mixed
    {
        $dto      = SimpleTokenRequest::fromArray($request->get);
        $token    = $dto->token;
        $tempData = $this->permitService->getVerifiedRequest($token);
        if ($token === '' || $tempData === null) {
            return new RedirectResponse('index.php');
        }
        $dtVon = new \DateTimeImmutable($tempData['datum_von'] ?? 'now');
        $dtBis = new \DateTimeImmutable($tempData['datum_bis'] ?? 'now');
        $this->renderer->render('checkout/summary', [
            'holidayNotice' => HolidayHtmlPresenter::formatHolidayNotice(
                $this->holidayService->getHolidaysInRange($dtVon, $dtBis),
            ),
            'opening' => HolidayHtmlPresenter::formatOpeningHours(
                $this->holidayService->getOpeningHoursDataForDateRange($dtVon, $dtBis),
            ),
            'tempData' => $tempData,
            'token'    => $token]);

        return null;
    }
}
