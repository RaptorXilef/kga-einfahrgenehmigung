<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Security\CsrfHelper;
use App\Application\View\HolidayHtmlPresenter;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Core\Service\HolidayService;
use App\Core\Service\MagicLinkService;
use App\Core\Service\PermitService;
use App\Infrastructure\Storage\JsonHelper;

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
        private HolidayService $holidayService,
        private MagicLinkService $magicLinkService,
        private MailServiceInterface $mailService,
        private PermitService $permitService,
        private RateLimiterInterface $rateLimiter,
        private StorageInterface $storage,
        private TemplateRenderer $renderer,
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
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // 0. Zentrale Sicherheitsprüfung: IP-Sperre
        if ($this->rateLimiter->isBlocked($ip)) {
            $msg = 'Zu viele Versuche. Die IP-Adresse wurde für 15 Minuten gesperrt.';
            \header('Location: history.php?sent=0&msg=' . \urlencode($msg));
            exit;
        }

        // 1. Globale CSRF-Prüfung für POST-Requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (! CsrfHelper::verify($post)) {
                $msg = 'Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.';
                \header('Location: history.php?sent=0&msg=' . \urlencode($msg));
                exit;
            }

            // SICHERHEIT: Logout wird erst verarbeitet, wenn CSRF-Schutz erfolgreich gegriffen hat
            if ($this->processLogout($post)) {
                return;
            }
        }

        $emailInSession = (string) ($_SESSION['user_history_email'] ?? '');

        // --- A. POST-AKTION: E-Mail für Magic-Link anfragen ---
        if (isset($post['request_link'])) {
            $email = \trim((string) ($post['email'] ?? ''));

            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            // E-Mail-Bombing verhindern!
            if ($this->rateLimiter->isBlocked($ip)) {
                \header('Location: history.php?sent=0&msg=' . \urlencode('Zu viele Anfragen. Bitte warten Sie 15 Minuten.'));
                exit;
            }

            $permits = $this->permitService->getHistoryByEmail($email);

            // Immer neutral reagieren, um E-Mail-Scraping zu verhindern
            if ($permits === []) {
                $this->rateLimiter->recordFailedAttempt($ip); // FIX: Spamversuche bestrafen
                $msg = 'Falls Genehmigungen zu dieser E-Mail existieren, wurde ein Code gesendet.';
                \header('Location: history.php?sent=1&msg=' . \urlencode($msg));
            } else {
                $this->rateLimiter->clearAttempts($ip);
                $data = $this->magicLinkService->createToken($email);

                // Nutzt nun die sichere, zentrale Config-Methode
                $link = $this->config->getBaseUrl() . 'history.php?token=' . $data['token'];

                // [x] sortiert
                $this->mailService->sendTemplate(
                    $email,
                    'Login-Code: Ihre Genehmigungen',
                    'magic_link',
                    [
                        'baseUrl'     => $this->config->getBaseUrl(),
                        'code'        => $data['code'],
                        'duration'    => $this->config->get('magic_link_duration'),
                        'link'        => $link,
                        'vereinsName' => $this->config->get('vereins_name'),
                    ],
                );
            }

            // Fehlversuch hier protokollieren, falls jemand wild E-Mails durchprobiert
            $this->rateLimiter->recordFailedAttempt($ip);

            // Einheitliche neutrale Meldung
            $msg = 'Falls Genehmigungen zu dieser E-Mail existieren, wurde ein Code gesendet.';
            \header('Location: history.php?sent=1&msg=' . \urlencode($msg));
            exit;
        }

        // --- B. POST-AKTION: Manueller Code-Submit ---
        if (isset($post['submit_code'])) {
            $verifiedEmail = $this->magicLinkService->verifyAny((string) ($post['login_code'] ?? ''));

            if ($verifiedEmail) {
                $this->rateLimiter->clearAttempts($ip);
                \session_regenerate_id(true);
                $_SESSION['user_history_email'] = $verifiedEmail;
                \header('Location: history.php'); // Erfolgreich eingeloggt -> Saubere URL
                exit;
            }

            // Fehlversuch protokollieren
            $this->rateLimiter->recordFailedAttempt($ip);

            \header('Location: history.php?sent=1&msg=' . \urlencode('Der Code ist ungültig oder abgelaufen.'));
            exit;
        }

        // --- C. GET-AKTION: Token-Verifizierung (Klick auf E-Mail Link) ---
        $displayMessage = (string) ($get['msg'] ?? '');
        $isSuccess      = ($get['sent'] ?? '') === '1';
        $currentStep    = ($get['sent'] ?? '0') === '1' ? 2 : 1;

        if (isset($get['token'])) {
            $currentStep   = 2; // Feld für manuellen Code soll sichtbar bleiben bei Fehler
            $verifiedEmail = $this->magicLinkService->verifyAny((string) $get['token']);

            if ($verifiedEmail) {
                $this->rateLimiter->clearAttempts($ip);
                \session_regenerate_id(true);
                $_SESSION['user_history_email'] = $verifiedEmail;
                \header('Location: history.php');
                exit;
            }

            // Fehlversuch protokollieren
            $this->rateLimiter->recordFailedAttempt($ip);

            $displayMessage = 'Der Link ist ungültig oder abgelaufen. Sie können den Code manuell eingeben.';
            $isSuccess      = false;
        }

        // --- D. PRINT-AKTION ---
        if (isset($get['action'], $get['code']) && $get['action'] === 'print') {
            $this->handlePrintAction((string) $get['code'], $emailInSession);

            return;
        }

        // --- E. Ansicht rendern ---
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
            // [x] sortiert
            $this->renderer->render('history_login', [
                'isSuccess' => $isSuccess,
                'message'   => $message,
                'step'      => $step, // Den Schritt ans Template geben
            ]);

            return;
        }

        // 1. Aktuelle Daten laden
        $permits = $this->permitService->getHistoryByEmail($email);

        // 2. Archive laden, falls angefordert (?load_archive=2025)
        $loadedYear = (int) ($get['load_archive'] ?? 0);

        if ($loadedYear > 0) {
            $arcCfg      = $this->config->get('storage_config')['permits_archive'];
            $yearFile    = \str_replace('{YEAR}', (string) $loadedYear, $arcCfg['file_pattern'] ?? $arcCfg['file']); // Fallback eingefügt
            $archivePath = $this->config->getStoragePath($yearFile);

            if (\file_exists($archivePath)) {
                $archiveData = JsonHelper::read($archivePath);
                // Filter für E-Mail im Archiv
                foreach ($archiveData as $item) {
                    if (\strtolower((string) $item['email']) !== \strtolower($email)) {
                        continue;
                    }

                    $permits[] = $this->storage->mapToEntity($item);
                }
            }
        }

        // --- Sortierung der Genehmigungen (Neueste zuerst) ---
        // Der Spaceship-Operator (<=>) funktioniert perfekt mit DateTime Objekten!
        \usort($permits, fn ($a, $b): int => $b->erstellt <=> $a->erstellt);

        // [x] sortiert
        $this->renderer->render('history_list', [
            'currentArchiveYear' => $loadedYear,
            'email'              => $email,
            'isSuccess'          => $isSuccess,
            'message'            => $message,
            'permits'            => $permits,
            'permitService'      => $this->permitService, // Für Überfälligkeits-Prüfung
        ]);
    }

    /**
     * Validiert den Zugriff und rendert die Druckansicht einer spezifischen Genehmigung.
     *
     * @param string $code           Der eindeutige Hash der Genehmigung.
     * @param string $emailInSession Die verifizierte E-Mail-Adresse aus der Session.
     */
    private function handlePrintAction(string $code, string $emailInSession): void
    {
        $permit = $this->storage->findByHash($code);
        if ($permit instanceof Permit && \strtolower($permit->owner->email) === \strtolower($emailInSession)) {
            // [x] sortiert
            $this->renderer->render('history_print_view', [
                'holidayNotice' => HolidayHtmlPresenter::formatHolidayNotice(
                    $this->holidayService->getHolidaysInRange(
                        $permit->validity->von,
                        $permit->validity->bis,
                    ),
                ),
                'opening_html' => HolidayHtmlPresenter::formatOpeningHours(
                    $this->holidayService->getOpeningHoursDataForDateRange(
                        $permit->validity->von,
                        $permit->validity->bis,
                    ),
                ),
                'permit' => $permit,
            ]);
        } else {
            \header('Location: history.php');
        }
    }

    /**
     * Verarbeitet den Logout-Prozess für die History-Sitzung.
     *
     * @param array<string, mixed> $post Entspricht $_POST
     *
     * @return bool True, wenn ein Logout durchgeführt und weitergeleitet wurde.
     */
    private function processLogout(array $post): bool
    {
        if (isset($post['action']) && $post['action'] === 'logout') {
            unset($_SESSION['user_history_email']);
            \header('Location: history.php');

            return true;
        }

        return false;
    }
}
