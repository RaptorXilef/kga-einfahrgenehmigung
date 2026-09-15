<?php

declare(strict_types=1);

namespace App\Application\Actions\Api\Shared;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\ApiDateInfoRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Core\Service\HolidayService;
use Throwable;

/**
 * Action für den API-Aufruf zur Abfrage der erlaubten Einfahrtszeiten.
 * Liefert STRUKTURIERTE DATEN (JSON) anstatt HTML.
 */
#[Route('POST', '/api/get_date_info')]
final readonly class GetDateInfoAction implements ViewActionInterface
{
    public function __construct(
        private HolidayService $holidayService,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = ApiDateInfoRequest::fromArray($request->input);

            $holidays = $this->holidayService->getHolidaysInRange($dto->von, $dto->bis);
            $openingData = $this->holidayService->getOpeningHoursDataForDateRange($dto->von, $dto->bis);

            return JsonResponse::success([
                'openingData' => $openingData,
                'holidays' => $holidays,
            ]);
        } catch (Throwable $e) {
            return JsonResponse::error($e->getMessage());
        }
    }
}
