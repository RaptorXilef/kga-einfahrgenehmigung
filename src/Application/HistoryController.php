<?php

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Core\Service\HolidayService;
use App\Core\Service\MagicLinkService;
use App\Core\Service\PermitService;

/**
 * Controller für die historische Antragsübersicht von Endnutzern.
 *
 * Realisiert passwortlose Authentifizierung via Magic-Link / Login-Code per E-Mail
 * und bündelt aktive Genehmigungen sowie historische Archivdaten für den Nutzer.
 *
 * Path: src/Application/HistoryController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class HistoryController
{
    public function __construct(
        private ConfigInterface $config,
        private PermitService $permitService,
        private MagicLinkService $magicLinkService,
        private MailServiceInterface $mailService,
        private HolidayService $holidayService,
    ) {
    }

    /**
     * Haupt-Request-Handler für die Benutzerhistorie.
     * Steuert den Login-Prozess (Token-Generierung, Code-Verifizierung), Logout-Routen,
     * Druckansichten und die Aggregration der Benutzerdaten.
     *
     * @param array<string, mixed> $get  Entspricht $_GET
     * @param array<string, mixed> $post Entspricht $_POST
     */
    public function handleRequest(array $get, array $post): void
    {
        if ($this->processLogout($get)) {
            return;
        }

        $emailInSession = (string) ($_SESSION['user_history_email'] ?? '');
        $displayMessage = (string) ($get['msg'] ?? '');
        $isSuccess      = ($get['sent'] ?? '') === '1';

        // NEU: Wir führen eine Variable für den aktuellen Anzeige-Schritt ein
        // 1 = E-Mail Abfrage, 2 = Code-Eingabe
        $currentStep = ($get['sent'] ?? '0') === '1' ? 2 : 1;

        if (isset($post['request_link'])) {
            $email   = \trim((string) ($post['email'] ?? ''));
            $permits = $this->permitService->getHistoryByEmail($email);

            if ($permits === []) {
                $msg = 'Zu dieser E-Mail wurden keine Genehmigungen gefunden.';
                \header('Location: history.php?sent=0&msg=' . \urlencode($msg));
            } else {
                $data = $this->magicLinkService->createToken($email);
                $link = $this->config->getBaseUrl() . 'history.php?token=' . $data['token'];

                $this->mailService->sendTemplate($email, 'Login-Code: Ihre Genehmigungen', 'magic_link', [
                    'link'        => $link,
                    'code'        => $data['code'],
                    'duration'    => $this->config->get('magic_link_duration'),
                    'vereinsName' => $this->config->get('vereins_name'),
                ]);

                $msg = 'Code wurde gesendet (gültig für ' . $this->config->get('magic_link_duration') . ' Min).';
                \header('Location: history.php?sent=1&msg=' . \urlencode($msg));
            }
            exit;
        }

        // --- B. POST-AKTION: Manueller Code-Submit ---
        if (isset($post['submit_code'])) {
            $verifiedEmail = $this->magicLinkService->verifyAny((string) ($post['login_code'] ?? ''));
            if ($verifiedEmail) {
                $_SESSION['user_history_email'] = $verifiedEmail;
                \header('Location: history.php'); // Erfolgreich eingeloggt -> Saubere URL
                exit;
            }

            // Fehlerfall: Zurück zum Eingabefeld mit Fehlermeldung
            \header('Location: history.php?sent=1&msg=' . \urlencode('Der Code ist ungültig oder abgelaufen.'));
            exit;
        }

        // 2. Nachricht & Status aus der URL holen (für die Anzeige nach Redirects)
        $displayMessage = (string) ($get['msg'] ?? '');
        $isSuccess      = ($get['sent'] ?? '') === '1';

        // --- C. GET-AKTION: Token-Verifizierung (Klick auf E-Mail Link) ---
        if (isset($get['token'])) {
            $currentStep   = 2; // Feld für manuellen Code soll sichtbar bleiben bei Fehler
            $verifiedEmail = $this->magicLinkService->verifyAny((string) $get['token']);

            if ($verifiedEmail) {
                $_SESSION['user_history_email'] = $verifiedEmail;
                \header('Location: history.php');
                exit;
            }
            $displayMessage = 'Der Link ist ungültig oder abgelaufen. Sie können den Code manuell eingeben.';
            $isSuccess      = false;
        }

        if (isset($get['action'], $get['code']) && $get['action'] === 'print') {
            $this->handlePrintAction((string) $get['code'], $emailInSession);

            return;
        }

        // Wir übergeben den $currentStep an die renderView
        $this->renderView($emailInSession, $displayMessage, $get, $isSuccess, $currentStep);
    }

    /**
     * Bereitet die Benutzeroberfläche (Login oder Datenliste) vor und lädt optionale Archivdaten.
     * Kombiniert Live-Daten mit historischen JSON-Jahresarchiven bei Bedarf.
     *
     * @param string               $email     E-Mail-Adresse des authentifizierten Nutzers.
     * @param string               $message   Status- oder Fehlermeldung für die UI.
     * @param array<string, mixed> $get       Entspricht $_GET (für Archiv-Filterung).
     * @param bool                 $isSuccess Flag für visuelle Erfolgsdarstellung.
     * @param int                  $step      Aktueller UI-Schritt (1 = E-Mail-Eingabe, 2 = Code-Eingabe).
     */
    private function renderView(string $email, string $message, array $get, bool $isSuccess, int $step): void
    {
        if ($email === '') {
            $this->render('history_login', [
                'message'   => $message,
                'isSuccess' => $isSuccess,
                'step'      => $step, // Den Schritt ans Template geben
                'settings'  => $this->getSettingsArray(),
            ]);

            return;
        }

        // 1. Aktuelle Daten laden
        $permits = $this->permitService->getHistoryByEmail($email);

        // 2. Archive laden, falls angefordert (?load_archive=2025)
        $loadedYear = (int) ($get['load_archive'] ?? 0);
        if ($loadedYear > 0) {
            $arcCfg      = $this->config->get('storage_config')['permits_archive'];
            $yearFile    = \str_replace('{YEAR}', (string) $loadedYear, $arcCfg['file_pattern']);
            $archivePath = $this->config->get('root_path') . '/' . $this->config->get('storage_path_prefix') . $yearFile;
            if (\file_exists($archivePath)) {
                $archiveData = \json_decode((string) \file_get_contents($archivePath), true) ?? [];
                // Filter für E-Mail im Archiv
                foreach ($archiveData as $item) {
                    if (\strtolower((string) $item['email']) !== \strtolower($email)) {
                        continue;
                    }

                    $permits[] = $this->permitService->arrayToEntity($item);
                }
            }
        }

        // --- Sortierung der Genehmigungen (Neueste zuerst) ---
        // Der Spaceship-Operator (<=>) funktioniert perfekt mit DateTime Objekten!
        \usort($permits, fn ($a, $b) => $b->erstellt <=> $a->erstellt);

        $this->render('history_list', [
            'permits'            => $permits,
            'email'              => $email,
            'settings'           => $this->getSettingsArray(),
            'currentArchiveYear' => $loadedYear,
            'message'            => $message,
            'isSuccess'          => $isSuccess,
            'config'             => $this->config,        // Für Fahrzeug-Icons
            'permitService'      => $this->permitService,  // Für Überfälligkeits-Prüfung
        ]);
    }

    /**
     * Verarbeitet den Logout-Prozess für die History-Sitzung.
     *
     * @param array<string, mixed> $get Entspricht $_GET
     *
     * @return bool True, wenn ein Logout durchgeführt und weitergeleitet wurde.
     */
    private function processLogout(array $get): bool
    {
        if (isset($get['action']) && $get['action'] === 'logout') {
            unset($_SESSION['user_history_email']);
            \header('Location: history.php');

            return true;
        }

        return false;
    }

    /**
     * Validiert den Zugriff und rendert die Druckansicht einer spezifischen Genehmigung.
     *
     * @param string $code           Der eindeutige Hash der Genehmigung.
     * @param string $emailInSession Die verifizierte E-Mail-Adresse aus der Session.
     */
    private function handlePrintAction(string $code, string $emailInSession): void
    {
        $permit = $this->permitService->getStorage()->findByHash($code);
        if ($permit && \strtolower($permit->owner->email) === \strtolower($emailInSession)) {
            $this->render('history_print_view', [
                'permit'        => $permit,
                'settings'      => $this->getSettingsArray(),
                'appRoot'       => $this->config->get('root_path'),
                'opening'       => $this->holidayService->getGeneralOpeningHoursText(),
                'holidayNotice' => $this->holidayService->getHolidaysInRangeText($permit->validity->von, $permit->validity->bis),
            ]);
        } else {
            \header('Location: history.php');
        }
    }

    /**
     * Liefert Konfigurations-Settings für das History-Frontend.
     *
     * @return array<string, mixed>
     */
    private function getSettingsArray(): array
    {
        return [
            'vereins_name'       => $this->config->get('vereins_name'),
            'jahresFarbe'        => $this->config->get('jahresFarbe'),
            'base_url'           => $this->config->getBaseUrl(),
            'terminkalender_url' => $this->config->get('terminkalender_url'),
            'opening_hours'      => $this->config->get('opening_hours'),
        ];
    }

    /**
     * Extrahiert Datenvariablen und bindet das History-Template ein.
     *
     * @param string               $template Name der .phtml Datei.
     * @param array<string, mixed> $data     Variablen für das Template.
     */
    private function render(string $template, array $data): void
    {
        $appRoot = (string) $this->config->get('root_path');
        \extract($data);
        include "{$appRoot}/templates/pages/{$template}.phtml";
    }
}
