<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\SimpleCodeRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Application\View\HolidayHtmlPresenter;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Core\Service\HolidayService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('history_print')]
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
        } catch (ValidationException $e) {
            return new RedirectResponse('history.php');
        }

        $code           = $dto->code;
        $emailInSession = (string) $this->sessionManager->getHistoryEmail();

        $permit = $this->storage->findByHash($code);
        if ($permit instanceof Permit && \strtolower($permit->getOwnerEmail()) === \strtolower($emailInSession)) {
            $this->renderer->render('history_print_view', [
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

        return new RedirectResponse('history.php');
    }
}
