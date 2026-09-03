<?php

declare(strict_types=1);

namespace App\Application\Actions\Frontend;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\SimpleCodeRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Application\View\HolidayHtmlPresenter;
use App\Application\View\TemplateRenderer;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Core\Security\Sanitizer;
use App\Core\Service\HolidayService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('GET', '/history_print')]
#[Route('POST', '/history_print')]
final readonly class HistoryPrintAction implements ViewActionInterface
{
    public function __construct(
        private HolidayService $holidayService,
        private SessionManager $sessionManager,
        private StorageInterface $storage,
        private TemplateRenderer $renderer,
    ) {
    }

    // TODO DOCBLOCK
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

        $permit = $this->storage->findByHash($code);

        // Vergleicht die E-Mails via Normalisierung (+ Aliase) für höchste Zuverlässigkeit
        if ($permit instanceof Permit && Sanitizer::normalizeEmail($permit->getOwnerEmail()) === Sanitizer::normalizeEmail($emailInSession)) {
            $this->renderer->render('frontend/history_print_view', [
                'holidayNotice' => HolidayHtmlPresenter::formatHolidayNotice(
                    $this->holidayService->getHolidaysInRange($permit->getValidFrom(), $permit->getValidUntil()),
                ),
                'opening_html' => HolidayHtmlPresenter::formatOpeningHours(
                    $this->holidayService->getOpeningHoursDataForDateRange($permit->getValidFrom(), $permit->getValidUntil()),
                ),
                'permit' => $permit,
            ]);

            return null;
        }

        return new RedirectResponse('history');
    }
}
