<?php

declare(strict_types=1);

namespace App\Modules\Permit\Presentation\View;

use DateTimeImmutable;
use RuntimeException;

/**
 * Presenter zur Aufbereitung aller Darstellungsdaten für das A4-PDF-Dokument.
 * Befreit templates/emails/permit_a4_document.phtml komplett von Berechnungs- und Datumslogik.
 */
final class PermitA4Presenter
{
    public static function createViewDto(
        string $fullIdentifier,
        string $templateKey,
        string $jahresFarbe,
        string $vereinsName,
        string $checkQrBase64,
        string $openingHtml,
        string $holidayNoticeHtml,
        string $name,
        DateTimeImmutable $validFrom,
        DateTimeImmutable $validUntil,
        string $kennzeichen,
        string $firma,
        string $parzelle,
        string $zweck,
        string $terminkalenderUrl,
        string $erstelltFormatted,
        string $baseUrl,
    ): PermitA4DocumentViewDto {
        $cleanBaseUrl = \rtrim(\trim($baseUrl), '/');
        if ($cleanBaseUrl === '') {
            throw new RuntimeException('Sicherheits-Abbruch: "base_url" fehlt für die PDF/E-Mail-Generierung.');
        }
        $safeBaseUrl = $cleanBaseUrl . '/';

        $isPermanent = \str_contains(\strtolower($templateKey), 'perm');
        $headerColor = $isPermanent ? '#3498db' : ($jahresFarbe !== '' ? $jahresFarbe : '#2ecc71');
        $titleHtml = $isPermanent ? 'DAUER&shy;EINFAHRT&shy;GENEHMIGUNG' : 'AUSNAHME&shy;GENEHMIGUNG';

        $qStart = (int) \ceil((int) $validFrom->format('n') / 3);
        $qEnd = (int) \ceil((int) $validUntil->format('n') / 3);
        $activeQuarters = \range($qStart, $qEnd);

        $quarters = [];
        for ($i = 1; $i <= 4; ++$i) {
            $isActive = \in_array($i, $activeQuarters, true);
            $quarters[] = [
                'label' => 'Q' . $i,
                'cssClass' => $isActive ? 'c-quarter--active' : 'c-quarter--inactive',
            ];
        }

        $openingTextHtml = \trim($openingHtml) !== '' ? $openingHtml : 'Siehe Aushang';

        $conditionValidityHtml = $isPermanent
            ? 'Gültig innerhalb der genehmigten Quartale.'
            : '<strong>Gilt NICHT</strong> während der Ruhezeiten, Sonn- und Feiertagen.';

        $conditionParkingHtml = $isPermanent
            ? 'Berechtigt zum Parken auf ausgewiesenen Flächen / Vereinshaus.'
            : 'Berechtigt zum Be- und Entladen, jedoch <strong>nicht zum Parken</strong>.';

        return new PermitA4DocumentViewDto(
            fullIdentifier: $fullIdentifier,
            headerColor: $headerColor,
            titleHtml: $titleHtml,
            vereinsName: $vereinsName,
            isPermanent: $isPermanent,
            quarters: $quarters,
            checkQrBase64: $checkQrBase64,
            openingTextHtml: $openingTextHtml,
            holidayNoticeHtml: $holidayNoticeHtml,
            name: $name,
            displayVon: $validFrom->format('d.m.Y'),
            displayBis: $validUntil->format('d.m.Y'),
            kennzeichen: $kennzeichen,
            firma: $firma,
            parzelle: $parzelle,
            zweck: $zweck,
            conditionValidityHtml: $conditionValidityHtml,
            conditionParkingHtml: $conditionParkingHtml,
            terminkalenderUrl: $terminkalenderUrl,
            erstellt: $erstelltFormatted,
            baseUrl: $safeBaseUrl,
        );
    }
}
