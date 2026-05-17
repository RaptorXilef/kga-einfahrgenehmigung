<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

// Path: src/Core/Service/PermitService.php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Owner;
use App\Core\Entity\Permit;
use App\Core\Entity\Status;
use App\Core\Entity\Validity;
use App\Core\Entity\Vehicle;

/**
 * Service zur Verwaltung des Genehmigungsprozesses. / Zentraler Service für Ausnahmegenehmigungen.
 *
 * Orchestriert die Erstellung, Validierung, Speicherung und Benachrichtigung.
 * Unterstützt PayPal-Verifizierung (Instant) und Banküberweisungen (Pending)
 * mit konfigurierbaren Sicherheits-Features und dynamischem Pricing.
 */
final readonly class PermitService
{
    public function __construct(
        private StorageInterface $storage,
        private MailServiceInterface $mailService,
        private ConfigInterface $config,
        private HolidayService $holidayService,
        private PaymentProviderInterface $paymentProvider,
        private VoucherService $voucherService,
        private ?\PDO $pdo,
    ) {
    }

    /**
     * Erstellt eine neue Genehmigung basierend auf Vorlagen. v0.14.0
     *
     * @param array<string, mixed> $data
     * @param bool                 $sendMails Steuert den sofortigen Mailversand.
     */
    public function createPermit(array $data, bool $sendMails = true): Permit
    {
        $this->validateEmail((string) ($data['email'] ?? ''));

        // Vorlagen-Logik laden
        $tKey      = (string) ($data['template_key'] ?? 'std_7');
        $templates = (array) $this->config->get('permit_templates', []);
        $template  = (array) ($templates[$tKey] ?? $templates['std_7']);

        // 1. Zeiträume bestimmen
        $startDate = new \DateTimeImmutable((string) ($data['datum_von'] ?? 'now'));

        // Automatische Berechnung der Tage aus der Vorlage
        $endDate = $startDate->modify('+' . $template['days'] . ' days');
        if ($template['days'] === 'custom') {
            $endDate = new \DateTimeImmutable((string) ($data['datum_bis'] ?? 'now'));
        }

        // 2. Preis bestimmen (Template-Preis oder Admin-Override) (pkw)
        $vehicleTypes = $this->config->get('vehicle_types', []);
        $defaultType  = ! empty($vehicleTypes) ? \array_key_first($vehicleTypes) : 'pkw';
        $typ          = (string) ($data['typ'] ?? $defaultType);

        $preis = isset($data['manual_price'])
            ? (float) $data['manual_price']
            : (float) ($template['prices'][$typ] ?? 0.0);

        // Code-Generierung
        do {
            $randomId = $this->generateV4Suffix();

            // 1. Kennzeichen formatieren für die Anzeige (B-HD 7398)
            $displayPlate = $this->formatLicensePlate((string) ($data['kennzeichen'] ?? ''));

            // 2. Identifier-Plate: Leerzeichen durch Bindestriche ersetzen (B-HD-7398)
            $identifierPlate = \str_replace(' ', '-', $displayPlate);

            // 3. Eindeutige Kennung bauen: ML-0371-B-HD-7398-6Y5C
            // Wir nehmen den Typ-Key als Teil des Codes, falls kein Kennzeichen da ist (z.B. "ABWASSER")
            $platePart = $identifierPlate !== '' ? $identifierPlate : \strtoupper($typ);

            $fullIdentifier = \sprintf(
                '%s-%s-%s-%s',
                $this->config->get('prefix', 'ML'),
                \str_pad((string) ($data['parzelle'] ?? '0'), 4, '0', \STR_PAD_LEFT),
                $platePart,
                $randomId,
            );

            // Wir prüfen, ob der Code bereits existiert (Storage oder Warteräume)
            // NEU: Globale Prüfung über alle Archive hinweg
        } while (! $this->isCodeGloballyUnique($fullIdentifier));

        /** @var array<string, string> $purposes */
        $purposes = (array) $this->config->get('purposes', []);
        $zweck    = (string) ($purposes[(string) ($data['zweck'] ?? '')] ?? 'Privat');

        // Value Objects-Instanziierung
        $permit = new Permit(
            code: $fullIdentifier,
            templateKey: $tKey, // WICHTIG: Speichert die Art der Genehmigung für später
            owner: new Owner(
                (string) $data['name'],
                (string) $data['email'],
                \str_pad((string) $data['parzelle'], 4, '0', \STR_PAD_LEFT),
            ),
            vehicle: new Vehicle($typ, $displayPlate, $data['firma'] ?? null),
            validity: new Validity($startDate, $endDate, $preis, $zweck),
            status: new Status((string) ($data['status'] ?? 'wartend')),
            erstellt: new \DateTimeImmutable(),
            internerKommentar: $data['internerKommentar'] ?? null,
        );

        if (! $this->storage->save($permit)) {
            throw new \RuntimeException('Speicherfehler.');
        }

        if ($sendMails) {
            $this->dispatchMails($permit, $randomId);
        }

        return $permit;
    }

    /**
     * Erstellt einen temporären Antrag, der erst bestätigt werden muss.
     *
     * @param array<string, mixed> $data
     */
    public function createPendingVerification(array $data): string
    {
        // 2. Kollisionsprüfung (Gleichzeitige Buchung der selben Parzelle)
        $this->validateNoCollisions(
            (string) ($data['parzelle'] ?? ''),
            new \DateTimeImmutable((string) ($data['datum_von'] ?? 'now')),
            new \DateTimeImmutable((string) ($data['datum_bis'] ?? 'now')),
        );

        $tKey                  = $data['template_key'] ?? 'std_7';
        $templates             = (array) $this->config->get('permit_templates', []);
        $template              = $templates[$tKey] ?? $templates['std_7'];
        $vehicleTypes          = (array) $this->config->get('vehicle_types', []);
        $defaultType           = ! empty($vehicleTypes) ? \array_key_first($vehicleTypes) : 'pkw';
        $typ                   = $data['typ'] ?? $defaultType;
        $data['preisSnapshot'] = (float) ($template['prices'][$typ] ?? ($template['prices'][$defaultType] ?? 0.0));

        $token     = \bin2hex(\random_bytes(32));
        $shortCode = \strtoupper(\substr(\bin2hex(\random_bytes(4)), 0, 6));

        $data['verification_token'] = $token;
        $data['verification_code']  = $shortCode;
        $hours                      = (int) $this->config->get('hours_pending_verify', 24);
        $data['expires']            = \time() + (3600 * $hours);

        // Wir speichern das in einer separaten Datei oder MySQL
        $path               = $this->getStoragePath('pending_verification');
        $allPending         = $this->loadJson($path);
        $allPending[$token] = $data;
        $this->saveJson($path, $allPending);

        $this->mailService->sendTemplate((string) $data['email'], 'E-Mail bestätigen', 'verify_email', [
            'name'        => (string) $data['name'],
            'verifyUrl'   => $this->config->getBaseUrl() . 'verify.php?token=' . $token,
            'code'        => $shortCode,
            'vereinsName' => $this->config->get('vereins_name'),
        ]);

        return $token;
    }

    // --- INTERNE LOGIK & HELFER ---

    private function getStoragePath(string $key): string
    {
        $cfg = $this->config->get('storage_config')[$key];

        return $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $cfg['file'];
    }

    /**
     * Prüft, ob für eine Parzelle bereits Genehmigungen im Zeitraum vorliegen.
     * Wir prüfen bestätigte Genehmigungen UND offene Verifizierungen.
     */
    private function validateNoCollisions(string $parzelle, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $parzelleFormatted = \str_pad($parzelle, 4, '0', \STR_PAD_LEFT);

        // 1. Check im Hauptspeicher (Storage Interface)
        foreach ($this->storage->getAll() as $permit) {
            if (
                $permit->owner->parzelle === $parzelleFormatted
                && $this->datesOverlap($permit->validity->von, $permit->validity->bis, $start, $end)
            ) {
                throw new \RuntimeException(
                    "Kollision: Für Parzelle {$parzelle} existiert bereits eine Genehmigung vom " .
                        $permit->validity->von->format('d.m.Y') . ' bis ' . $permit->validity->bis->format('d.m.Y') . '.',
                );
            }
        }

        // 2. Check in den ausstehenden E-Mail-Bestätigungen (Pending)
        $allPending = $this->loadJson($this->getStoragePath('pending_verification'));

        foreach ($allPending as $pending) {
            $pPlot  = \str_pad((string) ($pending['parzelle'] ?? ''), 4, '0', \STR_PAD_LEFT);
            $pStart = new \DateTimeImmutable((string) ($pending['datum_von'] ?? 'now'));
            $pEnd   = new \DateTimeImmutable((string) ($pending['datum_bis'] ?? 'now'));

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
        $pendingPath  = $this->getStoragePath('pending_verification');
        $verifiedPath = $this->getStoragePath('verified_pending');

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

        $data['actual_token'] = $token; // Wir legen den echten Key dazu

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
        $verifiedPath = $this->getStoragePath('verified_pending');
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
        $mapping    = $this->config->get('storage_config');
        $isPending  = \str_contains($path, 'pending_verification');
        $isVerified = \str_contains($path, 'verified_pending');

        if (($isPending && $mapping['pending_verification']['type'] === 'mysql')
            || ($isVerified && $mapping['verified_pending']['type'] === 'mysql')
        ) {

            $table = $isPending ? $mapping['pending_verification']['table'] : $mapping['verified_pending']['table'];
            if (! $this->pdo) {
                return;
            }

            $this->pdo->exec("DELETE FROM $table");
            $stmt = $this->pdo->prepare("INSERT INTO $table (token, expires, data) VALUES (?, ?, ?)");
            foreach ($data as $token => $item) {
                $stmt->execute([$token, $item['expires'] ?? 0, \json_encode($item)]);
            }

            return;
        }

        \file_put_contents($path, \json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    private function loadJson(string $path): array
    {
        $mapping    = $this->config->get('storage_config');
        $isPending  = \str_contains($path, 'pending_verification');
        $isVerified = \str_contains($path, 'verified_pending');

        if (($isPending && $mapping['pending_verification']['type'] === 'mysql')
            || ($isVerified && ($mapping['verified_pending']['type'] ?? 'json') === 'mysql')
        ) {

            $table = $isPending ? $mapping['pending_verification']['table'] : $mapping['verified_pending']['table'];
            if (! $this->pdo) {
                return [];
            }

            $stmt = $this->pdo->query("SELECT * FROM $table");
            $res  = [];
            foreach ($stmt->fetchAll() as $r) {
                $res[$r['token']]            = \json_decode((string) $r['data'], true);
                $res[$r['token']]['expires'] = (int) $r['expires'];
            }

            return $res;
        }

        if (! \file_exists($path)) {
            return [];
        }
        $data = (array) \json_decode((string) \file_get_contents($path), true) ?? [];

        if ($isPending) {
            $now  = \time();
            $data = \array_filter($data, fn ($item) => isset($item['expires']) && (int) $item['expires'] > $now);
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
                // NEU (Sicher gegen gelöschte Fahrzeugtypen):
                'typLabel' => (function ($typ, $config) {
                    $vConfigs = $config->get('vehicle_types', []);

                    return $vConfigs[$typ]['label'] ?? 'Fahrzeug: ' . \strtoupper($typ);
                })($permit->vehicle->typ, $this->config),
                'kennzeichen' => $permit->vehicle->kennzeichen,
                'firma'       => $permit->vehicle->firma ?? '',
                'von'         => $permit->validity->von->format('d.m.Y'),
                'bis'         => $permit->validity->bis->format('d.m.Y'),
                'zweck'       => $permit->validity->zweck,
                'adminLink'   => $this->config->getBaseUrl() . "admin.php?code={$permit->code}&token={$token}",
                'vereinsName' => $this->config->get('vereins_name'),
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

            // 3. DAS A4 DOKUMENT (KEINE DUPLIKATE MEHR!)
            $this->mailService->sendTemplate(
                $permit->owner->email,
                'Ausnahmegenehmigung: ' . $this->config->get('vereins_name'),
                'permit_a4_document',
                [
                    'fullIdentifier'    => $permit->code,
                    'von_formatted'     => $permit->validity->von->format('d.m.Y'),
                    'bis_formatted'     => $permit->validity->bis->format('d.m.Y'),
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
        // 1. Check in Haupt-Datenbank (Storage Interface macht das automatisch passend)
        if ($this->storage->findByHash($fullIdentifier) instanceof Permit) {
            return false;
        }

        $arcCfg = $this->config->get('storage_config')['permits_archive'];

        // 2. Check in den Archiven
        if ($arcCfg['type'] === 'mysql' && $this->pdo) {
            $stmt = $this->pdo->prepare("SELECT code FROM {$arcCfg['table']} WHERE code = ?");
            $stmt->execute([$fullIdentifier]);
            if ($stmt->fetch()) {
                return false;
            }
        } else {
            // JSON Archive (DYNAMISCH)
            $arcCfg     = $this->config->get('storage_config')['permits_archive'];
            $storageDir = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix');

            // Wir nehmen das Muster aus der Config (z.B. permits_archive_{YEAR}.json)
            // und ersetzen {YEAR} durch *, damit glob alle Jahre findet.
            $globPattern = \str_replace('{YEAR}', '*', (string) $arcCfg['file_pattern']);
            $archives    = \glob($storageDir . $globPattern);

            if ($archives !== false) {
                foreach ($archives as $archivePath) {
                    // Lade das Archiv
                    $archiveData = \json_decode((string) \file_get_contents($archivePath), true) ?? [];

                    // Da der Code im JsonStorage der "Key" auf der höchsten Ebene ist:
                    if (isset($archiveData[$fullIdentifier])) {
                        return false;
                    }
                }
            }
        }

        return true;
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
        $verifiedPath = $this->getStoragePath('verified_pending');
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
        $all = $this->loadJson($this->getStoragePath('verified_pending'));

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
            fn (Permit $permit): bool => \strtolower($permit->owner->email) === \strtolower($email),
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
        $archiveDeadline = (string) $this->config->get('archive_deadline', '02-01');
        if (\date('m-d') < $archiveDeadline) {
            return;
        }

        $lastYear = (int) \date('Y') - 1;
        // $cfg      = $this->config->get('storage_config')['permits'];
        $arcCfg = $this->config->get('storage_config')['permits_archive'];

        $mainPath   = $this->getStoragePath('permits');
        $all        = $this->loadJson($mainPath);
        $toArchive  = [];
        $stayInMain = [];

        foreach ($all as $code => $data) {
            // ROBUSTER JAHR-CHECK: Erkennt Jahr aus String ODER DateTime-Objekt
            $val  = $data['erstellt'] ?? 'now';
            $year = (int) ($val instanceof \DateTimeInterface
                ? $val->format('Y')
                : \substr((string) $val, 0, 4));

            if ($year <= $lastYear) {
                $toArchive[$code] = $data;
            } else {
                $stayInMain[$code] = $data;
            }
        }

        if (empty($toArchive)) {
            return;
        }

        // --- WEICHE: Wo wird archiviert? ---
        if ($arcCfg['type'] === 'mysql' && $this->pdo) {
            $sql = "REPLACE INTO {$arcCfg['table']} (code, templateKey, name, email, kennzeichen, parzelle, typ, firma, zweck, preisSnapshot, von, bis, status, erstellt, internerKommentar)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            foreach ($toArchive as $item) {
                $stmt->execute([
                    $item['code'],
                    $item['templateKey'],
                    $item['name'],
                    $item['email'],
                    $item['kennzeichen'],
                    $item['parzelle'],
                    $item['typ'],
                    $item['firma'],
                    $item['zweck'],
                    $item['preisSnapshot'],
                    $item['von'],
                    $item['bis'],
                    $item['status'],
                    $item['erstellt'],
                    $item['internerKommentar'],
                ]);
            }
        } else {
            // Klassisch JSON
            $yearPath = \str_replace('{YEAR}', (string) $lastYear, $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $arcCfg['file_pattern']);
            $existing = \file_exists($yearPath) ? (array) \json_decode((string) \file_get_contents($yearPath), true) : [];
            $this->saveJson($yearPath, \array_merge($existing, $toArchive));
        }

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

    /**
     * Öffentliche Brücke für die Migration, um Warteraum-Daten zu speichern.
     */
    public function savePendingData(string $category, array $data): void
    {
        $this->saveJson($this->getStoragePath($category), $data);
    }

    public function getMailService(): MailServiceInterface
    {
        return $this->mailService;
    }
}
