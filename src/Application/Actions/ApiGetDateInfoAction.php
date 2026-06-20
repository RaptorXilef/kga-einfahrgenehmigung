<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ApiDateInfoRequest;
use App\Application\Response\JsonResponse;
use App\Application\View\HolidayHtmlPresenter;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\HolidayService;

/**
 * Action für den API-Aufruf zur Abfrage der erlaubten Einfahrtszeiten
 * und Ruhetage für einen gewählten Zeitraum.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ApiGetDateInfoAction implements ViewActionInterface
{
    public function __construct(private HolidayService $holidayService)
    {
    }

    public function execute(array $requestData): mixed
    {
        try {
            $dto         = ApiDateInfoRequest::fromArray($requestData['input']);
            $holidays    = $this->holidayService->getHolidaysInRange($dto->von, $dto->bis);
            $openingData = $this->holidayService->getOpeningHoursDataForDateRange($dto->von, $dto->bis);

            $openingHtml = '<strong>⏰ Erlaubte Einfahrzeiten (Ruhezeiten beachten):</strong><br>' .
                'Das Befahren der Anlage ist ausschließlich zu folgenden Zeiten gestattet:<br>' .
                '<span style="color: var(--primary-color); font-weight: bold;">' .
                HolidayHtmlPresenter::formatOpeningHours($openingData) . '</span>';

            return JsonResponse::success([
                'openingHours'  => $openingHtml,
                'holidayNotice' => HolidayHtmlPresenter::formatHolidayNotice($holidays),
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage());
        }
    }
}
