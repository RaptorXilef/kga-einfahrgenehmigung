<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Interface für Zahlungsanbieter.
 *
 * Definiert die notwendigen Methoden zur Verifizierung und Abwicklung von Zahlungen.
 *
 * Path:      src/Contracts/Payment/PaymentProviderInterface.php
 */

declare(strict_types=1);

namespace App\Contracts\Payment;

interface PaymentProviderInterface
{
    /**
     * Erstellt eine Bestellung beim Anbieter und gibt die Order-ID zurück.
     */
    public function createOrder(float $amount): string|false;

    /**
     * Verifiziert eine Zahlung beim Anbieter und schließt diese ab.
     *
     * @param string $orderId        Die vom Client übermittelte Order-ID.
     * @param float  $expectedAmount Der Betrag, der laut deiner Config gezahlt werden muss.
     *
     * @return bool True, wenn die Zahlung erfolgreich verifiziert und abgeschlossen wurde.
     */
    public function captureOrder(string $orderId, float $expectedAmount): bool;
}
) : null;

        // Wenn über den Code nichts gefunden wurde, versuche es als Kennzeichen
        if ($permit === null && $code !== '') {
            $permit = $this->storage->findByLicensePlate($code);
        }

        // 2. Suche in verifizierten Anträgen (Warteraum 2) via PermitService
        // Wir nutzen den PermitService, um den Warteraum zu prüfen
        $tempRequest = $this->permitService->getVerifiedRequest($token);

        // Fall 1: Nichts eingegeben -> Suchmaske (Ordner-Pfad angepasst!)
        if ($code === '' && $tempRequest === null) {
            $this->render('check/search', ['error' => null]);

            return;
        }

        // Standard-Daten für die Header-Navigation (falls eingeloggt)
        $adminData = [
            'adminUser'  => (string) ($_SESSION['admin_user'] ?? 'Admin'),
            'adminLevel' => (int) ($_SESSION['admin_level'] ?? 1),
        ];

        // --- Logik für den nächsten befahrbaren Slot ---
        $nextAllowedSlotText = 'Keine weitere Einfahrt möglich.';
        $nextSlot            = $this->holidayService->getNextAvailableSlot($now);

        if ($nextSlot !== null) {
            // Prüfung: Ist der nächste Slot noch innerhalb der Genehmigungszeit?
            // Spezialfall: Letzter Tag / Ablaufprüfung
            if ($permit instanceof Permit && $nextSlot > $permit->validity->bis) {
                $nextAllowedSlotText = 'Die Gültigkeit endet, bevor die Anlage wieder befahren werden darf.';
            } else {
                // Normale Zeit-Formatierung
                $datePart = $nextSlot->format('d.m.Y');
                $today    = $now->format('d.m.Y');
                $tomorrow = $now->modify('+1 day')->format('d.m.Y');

                if ($datePart === $today) {
                    // "heute ab 15:00 Uhr"
                    $nextAllowedSlotText = 'heute ab ' . $nextSlot->format('H:i') . ' Uhr';
                } elseif ($datePart === $tomorrow) {
                    // "morgen ab 08:00 Uhr"
                    $nextAllowedSlotText = 'morgen ab ' . $nextSlot->format('H:i') . ' Uhr';
                } else {
                    // "am 04.05.2026 ab 08:00 Uhr"
                    $nextAllowedSlotText = 'am ' . $datePart . ' ab ' . $nextSlot->format('H:i') . ' Uhr';
                }
            }
        }

        // Fall 2: Warteraum / Bezahlseite
        if ($tempRequest !== null && ! $permit instanceof Permit) {
            $this->render('check/public', \array_merge($adminData, [
                'isWaitingForPayment' => true,
                'tempData'            => $tempRequest,
                'token'               => $token,
                'isDateValid'         => true,
                'isTimeAllowed'       => $this->holidayService->isTimeAllowedNow(),
                'allowedToday'        => $nextAllowedSlotText,
                'showAdminView'       => false,
                'permit'              => null,
            ]));

            return;
        }

        // Fall 3: Genehmigung gefunden
        if ($permit instanceof Permit) {
            $showAdminView = $this->determineViewPrivileges($permit, $get);
            // Pfade angepasst auf Unterordner check/
            $this->render($showAdminView ? 'check/admin' : 'check/public', \array_merge($adminData, [
                'permit'        => $permit,
                'isDateValid'   => $permit->isValid(),
                'isTimeAllowed' => $this->holidayService->isTimeAllowedNow(),
                'allowedToday'  => $nextAllowedSlotText, // Variable wird hier übergeben
                'showAdminView' => $showAdminView,
                'tempData'      => null,
            ]));

            return;
        }

        // Fall 4: Code nicht gefunden
        $this->render('check/search', ['error' => "Code '{$code}' nicht gefunden."]);
    }

    /**
     * Prüft, ob der Nutzer erweiterte Details sehen darf.
     *
     * @param array<string, mixed> $get
     */
    private function determineViewPrivileges(Permit $permit, array $get): bool
    {
        // A. Entwickler-Modus
        if ((bool) $this->config->get('admin_dev_mode', false)) {
            return true;
        }

        // B. Eingeloggter Admin (Session)
        if ($this->auth->isLoggedIn()) {
            return true;
        }

        // C. Token im Link (SHA256 Abgleich)
        $token     = (string) ($get['token'] ?? '');
        $geheimnis = (string) $this->config->get('geheimnis', '');
        $expected  = \hash('sha256', $permit->code . $geheimnis);

        return \hash_equals($expected, $token);
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettingsArray(): array
    {
        return [
            'vereins_name'  => $this->config->get('vereins_name'),
            'vehicle_types' => $this->config->get('vehicle_types'),
            'purposes'      => $this->config->get('purposes'),
            'opening_hours' => $this->config->get('opening_hours'),
            'jahresFarbe'   => $this->config->get('jahresFarbe'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $templatePath, array $data = []): void
    {
        $config   = $this->config;
        $appRoot  = (string) $config->get('root_path');
        $settings = $this->getSettingsArray();

        // 1. Array in einer Variable zwischenspeichern (löst den Fehler P1114)
        $templateData = \array_merge([
            'appRoot'  => $appRoot,
            'settings' => $settings,
            'config'   => $config,
        ], $data);

        // 2. Die Variable an extract übergeben
        \extract($templateData);

        include $appRoot . "/templates/pages/{$templatePath}.phtml";
    }
}
alidity->bis, $start, $end)
            ) {
                throw new \RuntimeException(
                    "Kollision: Für Parzelle {$parzelle} existiert bereits eine Genehmigung vom " .
                        $permit->von->format('d.m.Y') . ' bis ' . $permit->bis->format('d.m.Y') . '.',
                );
            }
        }

        // 2. Check in den ausstehenden E-Mail-Bestätigungen (Pending)
        $path       = $this->config->get('root_path') . '/storage/pending_verification.json';
        $allPending = $this->loadJson($path);

        foreach ($allPending as $pending) {
            $pStart = new \DateTimeImmutable((string) ($pending['datum_von'] ?? 'now'));
            $pEnd   = new \DateTimeImmutable((string) ($pending['datum_bis'] ?? 'now'));
            $pPlot  = \str_pad((string) ($pending['parzelle'] ?? ''), 4, '0', \STR_PAD_LEFT);

            // Nur prüfen, wenn die ausstehende Anfrage noch nicht abgelaufen ist
            if (
                $pPlot === $parzelleFormatted
                && (int) ($pending['expires'] ?? 0) > \time()
                && $this->datesOverlap($pStart, $pEnd, $start, $end)
            ) {
                throw new \RuntimeException(
                    "Hinweis: Für Parzelle {$parzelle} läuft bereits eine Anfrage für diesen Zeitraum. " .
                        'Bitte warten Sie 24h oder wählen Sie andere Daten.',
                );
            }
        }
    }

    /**
     * Die mathematische Formel für Zeit-Überschneidungen.
     */
    private function datesOverlap(
        \DateTimeImmutable $startA,
        \DateTimeImmutable $endA,
        \DateTimeImmutable $startB,
        \DateTimeImmutable $endB,
    ): bool {
        return $startA <= $endB && $endA >= $startB;
    }

    /**
     * Schritt 2: Verschiebt von unbestätigt (24h) nach verifiziert (48h).
     * Berücksichtigt nun Rabatte und Teilzahlungen durch Gutscheine.
     *
     * @return array<string, mixed>|null
     */
    public function confirmEmail(string $input): ?array
    {
        $pendingPath  = $this->config->get('root_path') . '/storage/pending_verification.json';
        $verifiedPath = $this->config->get('root_path') . '/storage/verified_pending.json';

        $allPending = $this->loadJson($pendingPath);
        $input      = \strtoupper(\trim($input));

        $matchedToken = null;

        // 1. Suche nach Lang-Token ODER Kurz-Code
        foreach ($allPending as $t => $d) {
            if (\strtoupper($t) === $input || \strtoupper((string) ($d['verification_code'] ?? '')) === $input) {
                $matchedToken = $t;

                break;
            }
        }

        // 2. Double-Click Check: Falls nicht mehr in 'pending', schaue in 'verified'
        if ($matchedToken === null) {
            $allVerified = $this->loadJson($verifiedPath);
            foreach ($allVerified as $t => $d) {
                if (\strtoupper($t) === $input || \strtoupper((string) ($d['verification_code'] ?? '')) === $input) {
                    return $d;
                }
            }

            return null;
        }

        // 2. Daten aus 'pending' extrahieren und dort löschen
        $token = $matchedToken;
        $data  = (array) $allPending[$token];
        unset($allPending[$token]);
        $this->saveJson($pendingPath, $allPending);

        // 3. Neue Ablaufzeit für Warteraum 2 setzen (z.B. 48h für Zahlung)
        $hours               = (int) $this->config->get('hours_pending_finalize', 48);
        $data['verified_at'] = \time();
        $data['expires']     = \time() + (3600 * $hours);

        // 4. GUTSCHEIN-LOGIK (ERWEITERT)
        $voucherCode = \trim((string) ($data['voucher'] ?? ''));
        if ($voucherCode !== '') {
            $voucher = $this->voucherService->useVoucher($voucherCode, $data);

            if ($voucher !== null) {
                // Berechne den Preis nach Abzug des Rabatts
                $finalPrice = $this->calculateDiscountedPrice((float) $data['preisSnapshot'], $voucher);

                // Fall A: Gutschein deckt alles (0,00 €)
                if ($finalPrice <= 0.0) {
                    $data['preisSnapshot'] = 0.0;
                    $data['status']        = 'bezahlt';

                    // Wir müssen es hier nicht in verified_pending speichern,
                    // sondern können es sofort finalisieren.
                    return ['finalised' => $this->finaliseRequest($token, 'bezahlt', 'Gutschein (Voll-Rabatt): ' . $voucherCode)];
                }

                // Fall B: Restbetrag bleibt offen (Teil-Rabatt)
                $data['preisSnapshot']   = $finalPrice;
                $data['voucher_applied'] = $voucherCode;
                $data['voucher_details'] = [
                    'type'  => $voucher['type'],
                    'value' => $voucher['value'],
                ];
            }
        }

        // 5. In Warteraum 2 (verified_pending) speichern
        $allVerified         = $this->loadJson($verifiedPath);
        $allVerified[$token] = $data;
        $this->saveJson($verifiedPath, $allVerified);

        return $data;
    }

    /**
     * Berechnet den Endpreis für einen Gutschein.
     * Robust gegen fehlende Array-Keys.
     */
    public function calculateDiscountedPrice(float $originalPrice, array $voucher): float
    {
        $type  = $voucher['type'] ?? 'free';
        $value = (float) ($voucher['value'] ?? 0.0);

        $newPrice = match ($type) {
            'free'    => 0.0,
            'fixed'   => $value,
            'percent' => $originalPrice * (1 - ($value / 100)),
            default   => $originalPrice,
        };

        return (float) \max(0.0, $newPrice);
    }

    /**
     * Schritt 3: Der eigentliche Umzug in die Datenbank und Mail-Versand.
     */
    public function finaliseRequest(string $token, string $status = 'wartend', ?string $kommentar = null): Permit
    {
        $verifiedPath = $this->config->get('root_path') . '/storage/verified_pending.json';
        $allVerified  = $this->loadJson($verifiedPath);

        if (! isset($allVerified[$token])) {
            throw new \RuntimeException('Antragssitzung abgelaufen oder bereits abgeschlossen.');
        }

        $data                      = (array) $allVerified[$token];
        $data['status']            = $status;
        $data['internerKommentar'] = $kommentar;

        // Echte Permit erstellen
        $permit = $this->createPermit($data, true);

        // Aus Warteraum löschen
        unset($allVerified[$token]);
        $this->saveJson($verifiedPath, $allVerified);

        return $permit;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function saveJson(string $path, array $data): void
    {
        \file_put_contents($path, \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    private function loadJson(string $path): array
    {
        if (! \file_exists($path)) {
            return [];
        }

        $data = (array) \json_decode((string) \file_get_contents($path), true) ?? [];

        // --- AUTO-CLEANUP für Pending-Files ---
        if (\str_contains($path, 'pending_verification')) {
            $now           = \time();
            $originalCount = \count($data);

            // Entferne alle abgelaufenen Einträge
            $data = \array_filter(
                $data,
                fn(array $item): bool => isset($item['expires']) && (int) $item['expires'] > $now,
            );

            // Wenn etwas gelöscht wurde, Datei direkt bereinigen
            if (\count($data) !== $originalCount) {
                $this->saveJson($path, $data);
            }
        }

        return $data;
    }

    /**
     * Orchestriert den Versand der unterschiedlichen E-Mails.
     */
    private function dispatchMails(Permit $permit, string $shortCode): void
    {
        $zeitraum  = "{$permit->validity->von->format('d.m.Y')} bis {$permit->validity->bis->format('d.m.Y')}";
        $geheimnis = (string) $this->config->get('geheimnis', '');
        $token     = \hash('sha256', $permit->code . $geheimnis);
        $opening   = $this->holidayService->getTodayAllowedSlots();

        // --- 1. MAIL AN VORSTAND (Immer senden) ---
        $this->mailService->sendTemplate(
            $this->config->get('mail')['recipients'][$this->config->isTestMode() ? 'test' : 'live'],
            "[{$permit->code}] - {$zeitraum} - {$permit->owner->name}",
            'board_notification',
            [
                'fullIdentifier' => $permit->code,
                'name'           => $permit->owner->name,
                'email'          => $permit->owner->email ?: 'Keine angegeben',
                'parzelle'       => $permit->owner->parzelle,
                'typLabel'       => $this->config->get('vehicle_types')[$permit->vehicle->typ] ?? $permit->vehicle->typ,
                'kennzeichen'    => $permit->vehicle->kennzeichen,
                'firma'          => $permit->vehicle->firma ?? '',
                'von'            => $permit->validity->von->format('d.m.Y'),
                'bis'            => $permit->validity->bis->format('d.m.Y'),
                'zweck'          => $permit->validity->zweck,
                'adminLink'      => $this->config->getBaseUrl() . "admin.php?code={$permit->code}&token={$token}",
                'vereinsName'    => $this->config->get('vereins_name'),
            ],
        );

        // --- MAIL AN NUTZER (Nur wenn E-Mail vorhanden ist) ---
        if (! empty(\trim($permit->owner->email))) {

            // 2. ZAHLUNGSAUFFORDERUNG (Nur wenn noch nicht bezahlt)
            if ($permit->status->current !== 'bezahlt') {
                $usage     = $this->generateUsageText($permit, $shortCode);
                $epcQrData = $this->generateEpcData($permit->validity->preisSnapshot, $usage);

                $this->mailService->sendTemplate(
                    $permit->owner->email,
                    "Zahlung erforderlich: {$permit->code}",
                    'payment_request',
                    [
                        'name'           => $permit->owner->name,
                        'fullIdentifier' => $permit->code,
                        'betrag'         => \number_format($permit->validity->preisSnapshot, 2, ',', '.') . ' €',
                        'dueDate'        => (new \DateTimeImmutable())->modify('+14 days')->format('d.m.Y'),
                        'kontoinhaber'   => $this->config->get('kontoinhaber'),
                        'iban'           => $this->config->get('iban'),
                        'usage'          => $usage,
                        'epcData'        => \urlencode($epcQrData),
                    ],
                );
            }

            // 3. DAS A4 DOKUMENT
            $this->mailService->sendTemplate(
                $permit->owner->email,
                'Ausnahmegenehmigung: ' . $this->config->get('vereins_name'),
                'permit_a4_document',
                [
                    'fullIdentifier'    => $permit->code,
                    'von'               => $permit->validity->von,
                    'bis'               => $permit->validity->bis,
                    'kennzeichen'       => $permit->vehicle->kennzeichen,
                    'firma'             => $permit->vehicle->firma ?? '',
                    'parzelle'          => $permit->owner->parzelle,
                    'zweck'             => $permit->validity->zweck,
                    'templateKey'       => $permit->templateKey,
                    'vereinsName'       => $this->config->get('vereins_name'),
                    'jahresFarbe'       => $this->config->get('jahresFarbe'),
                    'opening'           => $opening,
                    'terminkalenderUrl' => $this->config->get('terminkalender_url'),
                    'erstellt'          => $permit->erstellt->format('d.m.Y H:i'),
                    'checkUrl'          => \urlencode($this->config->getBaseUrl() . 'check.php?code=' . $permit->code),
                    'config'            => $this->config,
                ],
            );
        }
    }

    private function generateUsageText(Permit $permit, string $shortCode): string
    {
        $nameParts = \explode(' ', $permit->owner->name);
        $vorname   = $nameParts[0] ?? 'Unbekannt';
        $nachname  = $nameParts[\count($nameParts) - 1] ?? 'Unbekannt';

        return "EFG-{$nachname}-{$vorname}-{$shortCode}";
    }

    private function generateEpcData(float $amount, string $reference): string
    {
        // SEPA EPC-QR-Code (BezahlCode) Standard
        return "BCD\n001\n1\nSCT\n" .
            $this->config->get('bic') . "\n" .
            $this->config->get('kontoinhaber') . "\n" .
            $this->config->get('iban') . "\n" .
            'EUR' . \number_format($amount, 2, '.', '') . "\n" .
            "\n" . // Purpose Code leer
            "\n" . // Structured Reference leer
            $reference;
    }

    /**
     * Formatiert Kennzeichen (z.B. BHD7398 -> B-HD 7398).
     * Erkennt manuelle Bindestriche und unterstützt 4-er Blöcke (LL-LL).
     * Unterstützt jetzt auch E- und H-Zusätze am Ende.
     */
    private function formatLicensePlate(string $plate): string
    {
        $original = \trim(\strtoupper($plate));
        if ($original === '') {
            return '';
        }

        // 1. Wenn der Nutzer bereits ein Minus gesetzt hat -> Automatik deaktivieren
        if (\str_contains($original, '-')) {
            // Nur sicherstellen, dass zwischen letztem Buchstaben und Zahl ein Leerzeichen ist
            return (string) \preg_replace('/([A-Z])(\d)/', '$1 $2', $original);
        }

        // 2. Komplettreinigung für die Automatik (nur Buchstaben und Zahlen)
        $val = (string) \preg_replace('/[^A-Z0-9]/', '', $original);

        // 3. Sonderfall: 4 Buchstaben am Anfang (z.B. BBDW123E -> BB-DW 123E)
        if (\preg_match('/^([A-Z]{2})([A-Z]{2})(\d{1,4}[E|H]?)$/', $val, $matches)) {
            return "{$matches[1]}-{$matches[2]} {$matches[3]}";
        }

        // 4. Berlin-Priorität (B-XX 1234E)
        if (\preg_match('/^(B)([A-Z]{1,2})(\d{1,4}[E|H]?)$/', $val, $matches)) {
            return "{$matches[1]}-{$matches[2]} {$matches[3]}";
        }

        // 5. Standard: 1-3 Buchstaben (Region) + 1-2 Buchstaben + Zahlen (+E/H)
        if (\preg_match('/^([A-Z]{1,3})([A-Z]{1,2})(\d{1,4}[E|H]?)$/', $val, $matches)) {
            return "{$matches[1]}-{$matches[2]} {$matches[3]}";
        }

        // 6. Fallback: Region + Zahlen (+E/H)
        return (string) \preg_replace('/^([A-Z]{1,3})(\d{1,4}[E|H]?)$/', '$1 $2', $val);
    }

    /**
     * Generiert den neuen v4 Code: [PREFIX]-[YY]-[0000]-[RAND]
     */
    private function generateV4Suffix(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $res   = '';
        // Von 4 auf 6 Zeichen erhöht für 1400 Parzellen / 10 Jahre Sicherheit
        for ($i = 0; $i < 6; ++$i) {
            $res .= $chars[\random_int(0, \strlen($chars) - 1)];
        }

        return $res;
    }

    private function validateEmail(string $email): void
    {
        // Wenn das Feld leer ist, überspringen wir die Prüfung (da optional)
        if (\trim($email) === '') {
            return;
        }

        if (! \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Die eingegebene E-Mail-Adresse ist ungültig.');
        }
    }

    /**
     * Prüft, ob ein Code in der aktuellen DB oder in irgendwelchen Archiven existiert.
     */
    private function isCodeGloballyUnique(string $fullIdentifier): bool
    {
        // 1. Check in der aktiven Haupt-Datenbank (Storage)
        if ($this->storage->findByHash($fullIdentifier) instanceof Permit) {
            return false;
        }

        // 2. Check in allen Archiven (daten_XXXX.json)
        $storageDir = $this->config->get('root_path') . '/storage/';
        $archives   = \glob($storageDir . 'daten_*.json');

        if ($archives !== false) {
            foreach ($archives as $archivePath) {
                // Lade das Archiv
                $archiveData = \json_decode((string) \file_get_contents($archivePath), true) ?? [];

                // Da der Code im JsonStorage der "Key" auf der höchsten Ebene ist:
                if (isset($archiveData[$fullIdentifier])) {
                    return false; // Code wurde im Archiv gefunden!
                }
            }
        }

        return true; // Code ist nirgendwo bekannt
    }

    /**
     * Aktiviert eine Genehmigung manuell (Zahlungseingang bestätigt).
     */
    public function manualActivate(string $code, ?string $grund = null): bool
    {
        $permit = $this->storage->findByHash($code);
        if (! $permit instanceof Permit) {
            return false;
        }

        $updated = new Permit(
            $permit->code,
            $permit->templateKey,
            $permit->owner,
            $permit->vehicle,
            $permit->validity,
            new Status(
                'bezahlt', // Status-Update
                $permit->status->isSuspended,
                $permit->status->suspensionReason,
            ),
            $permit->erstellt,
            $grund ?? $permit->internerKommentar, // Grund übernehmen
        );

        return $this->storage->save($updated);
    }

    /**
     * Schließt eine PayPal-Zahlung ab und finalisiert den Antrag.
     * NEU in v0.12.0: Nutzt das Token, um im Warteraum 2 zu suchen.
     */
    public function completePayment(string $token, string $orderId): bool
    {
        $verifiedPath = $this->config->get('root_path') . '/storage/verified_pending.json';
        $allVerified  = $this->loadJson($verifiedPath);

        if (! isset($allVerified[$token])) {
            return false;
        }

        $data = (array) $allVerified[$token];

        // Zahlung bei PayPal verifizieren
        if ($this->paymentProvider->captureOrder($orderId, (float) $data['preisSnapshot'])) {
            // Wenn erfolgreich -> In die echte Datenbank verschieben
            $this->finaliseRequest($token, 'bezahlt', 'Bezahlt via PayPal');

            return true;
        }

        return false;
    }

    /**
     * Ermittelt den Überfälligkeits-Status.
     * 0 = Pünktlich
     * 1 = Zahlungsziel überschritten (Gelbe Warnung)
     * 2 = Benachrichtigungs-Zeitraum überschritten (Roter Alarm für Buchhaltung)
     */
    public function getOverdueLevel(Permit $permit): int
    {
        if ($permit->status->current === 'bezahlt') {
            return 0;
        }

        $now        = new \DateTimeImmutable();
        $dueDays    = (int) $this->config->get('payment_due_days', 14);
        $notifyDays = (int) $this->config->get('payment_due_days_notify', 2);

        $userDeadline        = $permit->erstellt->modify("+{$dueDays} days");
        $staffAlertThreshold = $userDeadline->modify("+{$notifyDays} days");

        if ($now > $staffAlertThreshold) {
            return 2; // Stufe: Buchhaltung informieren
        }

        if ($now > $userDeadline) {
            return 1; // Stufe: Zahlungsziel überschritten
        }

        return 0;
    }

    public function getVoucherService(): VoucherService
    {
        return $this->voucherService;
    }

    /**
     * Lädt einen verifizierten, aber noch nicht finalisierten Antrag.
     *
     * @return array<string, mixed>|null
     */
    public function getVerifiedRequest(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $path = $this->config->get('root_path') . '/storage/verified_pending.json';
        $all  = $this->loadJson($path);

        return (array) ($all[$token] ?? null) ?: null;
    }

    /**
     * Findet alle finalisierten Genehmigungen einer E-Mail-Adresse.
     *
     * @return Permit[]
     */
    public function getHistoryByEmail(string $email): array
    {
        $all = $this->storage->getAll();

        return \array_filter(
            $all,
            fn(Permit $permit): bool => \strtolower($permit->owner->email) === \strtolower($email),
        );
    }

    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }

    /**
     * Prüft, welche Quartale (1-4) vom Zeitraum der Genehmigung berührt werden.
     *
     * @return array<int>
     */
    public function getCoveredQuarters(Permit $permit): array
    {
        $startQ = (int) \ceil((int) $permit->validity->von->format('n') / 3);
        $endQ   = (int) \ceil((int) $permit->validity->bis->format('n') / 3);

        // Wenn es über ein Jahr hinausgeht, müssten wir mehr tun,
        // aber für Dauerkarten innerhalb eines Kalenderjahres reicht:
        return \range($startQ, $endQ);
    }

    /**
     * Prüft und führt die Jahres-Archivierung durch v0.16.0
     */
    public function checkAndArchive(): void
    {
        $archiveDeadline = (string) $this->config->get('archive_deadline', '02-01'); // Standard 1. Feb
        if (\date('m-d') < $archiveDeadline) {
            return;
        }

        $lastYear = (int) \date('Y') - 1;
        $mainPath = $this->config->get('root_path') . '/storage/daten.json';
        $all      = $this->loadJson($mainPath);

        $toArchive  = [];
        $stayInMain = [];

        foreach ($all as $code => $data) {
            $year = (int) \substr((string) $data['erstellt'], 0, 4);
            if ($year <= $lastYear) {
                $toArchive[$code] = $data;

                continue;
            }
            $stayInMain[$code] = $data;
        }

        if ($toArchive === []) {
            return;
        }

        $yearPath = $this->config->get('root_path') . "/storage/daten_{$lastYear}.json";
        // Bestehendes Archiv laden oder neu erstellen
        $existingYear = \file_exists(
            $yearPath,
        ) ? (array) \json_decode((string) \file_get_contents($yearPath), true) : [];
        $newYearData = \array_merge($existingYear, $toArchive);

        $this->saveJson($yearPath, $newYearData);
        $this->saveJson($mainPath, $stayInMain);
    }

    /**
     * Sperrt oder entsperrt eine Genehmigung
     */
    public function toggleSuspension(string $code, bool $status, ?string $reason = null): bool
    {
        $permit = $this->storage->findByHash($code);
        if (! $permit instanceof Permit) {
            return false;
        }

        $updated = new Permit(
            $permit->code,
            $permit->templateKey,
            $permit->owner,
            $permit->vehicle,
            $permit->validity,
            new Status(
                $permit->status->current,
                $status,
                $reason,
            ),
            $permit->erstellt,
            $permit->internerKommentar,
        );

        return $this->storage->save($updated);
    }

    /**
     * Hilfsmethode für Controller, um Rohdaten in eine Entität zu wandeln
     *
     * @param array<string, mixed> $data
     */
    public function arrayToEntity(array $data): Permit
    {
        return $this->storage->mapToEntity($data);
    }
}
