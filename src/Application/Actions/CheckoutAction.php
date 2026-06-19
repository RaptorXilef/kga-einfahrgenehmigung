<?php

declare(strict_types=1);

namespace App\Application\Actions;

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
 * Path: src/Application/Actions/CheckoutAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
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
    public function execute(array $requestData): void
    {
        // FIX: Auch hier auf ['get'] zugreifen!
        $token    = (string) ($requestData['get']['token'] ?? '');
        $tempData = $this->permitService->getVerifiedRequest($token);

        if ($token === '' || $tempData === null) {
            // Entweder Link falsch oder Session abgelaufen/bereits bezahlt
            \header('Location: index.php');
            exit;
        }

        // Daten für die Feiertage berechnen
        $dtVon = new \DateTimeImmutable($tempData['datum_von'] ?? 'now');
        $dtBis = new \DateTimeImmutable($tempData['datum_bis'] ?? 'now');

        // [x] sortiert
        $this->renderer->render('checkout/summary', [
            'holidayNotice' => HolidayHtmlPresenter::formatHolidayNotice(
                $this->holidayService->getHolidaysInRange($dtVon, $dtBis),
            ),
            // Dem Template die Zeiten übergeben
            'opening' => HolidayHtmlPresenter::formatOpeningHours(
                $this->holidayService->getOpeningHoursDataForDateRange($dtVon, $dtBis),
            ),
            'tempData' => $tempData,
            'token'    => $token,
        ]);
    }
}
