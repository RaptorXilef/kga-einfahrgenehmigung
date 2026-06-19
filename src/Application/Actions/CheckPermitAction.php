<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\View\HolidayHtmlPresenter;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\Permit;
use App\Core\Service\AuthService;
use App\Core\Service\HolidayService;

/**
 * Action zur Überprüfung von Genehmigungen und Kennzeichen.
 * Erlaubt die öffentliche und administrative Abfrage von Gültigkeiten (z.B. via QR-Code).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class CheckPermitAction implements ViewActionInterface
{
    public function __construct(
        private AuthService $auth,
        private ConfigInterface $config,
        private GroupRepositoryInterface $groupRepository,
        private HolidayService $holidayService,
        private StorageInterface $storage,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * TODO DOCBLOCK
     */
    public function execute(array $requestData): mixed
    {
        // DTO statt rohem $requestData['get']
        $dto  = \App\Application\DTO\SimpleCodeRequest::fromArray($requestData['get'] ?? []);
        $code = $dto->code;
        $now  = new \DateTimeImmutable();

        // 1. Suche in echten Permits (zuerst via Code/Hash)
        $permit = $code !== '' ? $this->storage->findByHash($code) : null;

        // Wenn über den Code nichts gefunden wurde, versuche es als Kennzeichen
        if (! $permit instanceof Permit && $code !== '') {
            $permit = $this->storage->findByLicensePlate($code);
        }

        // Fall 1: Nichts eingegeben -> Suchmaske
        if ($code === '') {
            // Bei fehlendem Code:
            $this->renderer->render('check/search', ['error' => null]);

            return null;
        }

        // Standard-Daten für die Header-Navigation (falls eingeloggt)
        $adminData = [
            'adminUser'  => $this->auth->getUsername(),
            'adminId'    => $this->auth->getUserId(),
            'adminGroup' => $this->auth->getGroup(),
        ];

        // --- Logik für den nächsten befahrbaren Slot ---
        $nextAllowedSlotText = 'Keine weitere Einfahrt möglich.';
        $nextSlot            = $this->holidayService->getNextAvailableSlot($now);

        if ($nextSlot instanceof \DateTimeImmutable) {
            // Prüfung: Ist der nächste Slot noch innerhalb der Genehmigungszeit?
            // Spezialfall: Letzter Tag / Ablaufprüfung
            if ($permit instanceof Permit && $nextSlot > $permit->getValidUntil()) {
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

        // Fall 2: Genehmigung gefunden
        if ($permit instanceof Permit) {
            $showAdminView = $this->determineViewPrivileges($permit, $dto->token);

            // Config auslesen
            $requirePayment = (bool) $this->config->get('require_payment_for_validity', false);

            // [x] sortiert
            // Pfade angepasst auf Unterordner check/
            $this->renderer->render($showAdminView ? 'check/admin' : 'check/public', \array_merge(
                $adminData,
                [
                    'permit'          => $permit,
                    'allowedToday'    => $nextAllowedSlotText,
                    'auth'            => $this->auth,
                    'groupRepository' => $this->groupRepository,
                    'holidayNotice'   => \implode(', ', $this->holidayService->getHolidaysInRange(
                        $permit->getValidFrom(),
                        $permit->getValidUntil(),
                    )),
                    'isDateValid'   => $permit->isValid($requirePayment),
                    'isTimeAllowed' => $this->holidayService->isTimeAllowedNow(),
                    'opening'       => HolidayHtmlPresenter::formatOpeningHours(
                        $this->holidayService->getOpeningHoursDataForDateRange(
                            $permit->getValidFrom(),
                            $permit->getValidUntil(),
                        ),
                    ),
                    'showAdminView'  => $showAdminView,
                    'userRepository' => $this->userRepository,
                ],
            ));

            return null;
        }

        $this->renderer->render('check/search', ['error' => "Code '{$code}' nicht gefunden."]);

        return null;
    }

    /**
     * Bestimmt die Rechte des aktuellen Betrachters für die Detailansicht.
     * Evaluierte Bedingungen: Admin-Dev-Mode aktiv, Admin eingeloggt oder gültiger Signatur-Hash.
     *
     * @param Permit $permit Das zu prüfende Genehmigungs-Objekt.
     *
     * @return bool True, wenn erweiterte Admin-Informationen angezeigt werden dürfen.
     */
    private function determineViewPrivileges(Permit $permit, string $token): bool
    {
        // A. Entwickler-Modus
        if ((bool) $this->config->get('admin_dev_mode', false)) {
            return true;
        }

        // B. Eingeloggter Admin (Session)
        if ($this->auth->isLoggedIn()) {
            return true;
        }

        $geheimnis = (string) $this->config->get('geheimnis', '');

        // Verhindere Bypass-Berechnungen durch unkonfiguriertes System-Geheimnis!
        if ($geheimnis === '') {
            return false;
        }

        $expected = \hash_hmac('sha256', $permit->code, $geheimnis);

        return \hash_equals($expected, $token);
    }
}
