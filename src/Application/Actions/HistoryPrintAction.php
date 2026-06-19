<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleCodeRequest;
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
final readonly class HistoryPrintAction implements ViewActionInterface
{
    public function __construct(
        private HolidayService $holidayService,
        private StorageInterface $storage,
        private TemplateRenderer $renderer,
    ) {
    }

    // TODO DOCBLOCK
    /**
     * Validiert den Zugriff und rendert die Druckansicht einer spezifischen Genehmigung.
     */
    public function execute(array $requestData): mixed
    {
        $dto  = SimpleCodeRequest::fromArray($requestData['get'] ?? []);
        $code = $dto->code;

        $emailInSession = (string) ($_SESSION['user_history_email'] ?? '');

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
        } else {
            \header('Location: history.php');
            exit;
        }

        return null;
    }
}
