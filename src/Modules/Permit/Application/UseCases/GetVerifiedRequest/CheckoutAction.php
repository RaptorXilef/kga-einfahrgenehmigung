<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetVerifiedRequest;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\View\HolidayHtmlPresenter;
use App\Application\View\TemplateRenderer;
use App\Modules\Permit\Application\Services\HolidayService;
use DateTimeImmutable;
use Exception;

#[Route('GET', '/checkout')]
final readonly class CheckoutAction implements ViewActionInterface
{
    public function __construct(
        private HolidayService $holidayService,
        private GetVerifiedRequestHandler $getVerifiedHandler,
        private TemplateRenderer $renderer,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = CheckoutRequest::fromArray($request->get);
        } catch (Exception) {
            return new RedirectResponse('/');
        }

        $token = $dto->token;
        $tempData = $this->getVerifiedHandler->handle(new GetVerifiedRequestQuery($token));

        if ($tempData === null) {
            return new RedirectResponse('/');
        }

        $dtVon = new DateTimeImmutable($tempData['datum_von'] ?? 'now');
        $dtBis = new DateTimeImmutable($tempData['datum_bis'] ?? 'now');

        $html = $this->renderer->render('frontend/checkout_summary', [
            'holidayNotice' => HolidayHtmlPresenter::formatHolidayNotice(
                $this->holidayService->getHolidaysInRange($dtVon, $dtBis),
            ),
            'opening' => HolidayHtmlPresenter::formatOpeningHours(
                $this->holidayService->getOpeningHoursDataForDateRange($dtVon, $dtBis),
            ),
            'tempData' => $tempData,
            'token' => $token,
        ]);

        return new HtmlResponse($html);
    }
}
