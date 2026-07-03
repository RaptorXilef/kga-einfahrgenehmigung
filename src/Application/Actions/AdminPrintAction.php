<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;

use App\Application\DTO\SimpleCodeRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\View\HolidayHtmlPresenter;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\Permit;
use App\Core\Service\AuthService;
use App\Core\Service\HolidayService;

/**
 * Action zum Rendern der administrativen Druckansicht.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('admin_print')]
final readonly class AdminPrintAction implements ViewActionInterface
{
    public function __construct(
        private AuthService $auth,
        private GroupRepositoryInterface $groupRepository,
        private HolidayService $holidayService,
        private StorageInterface $storage,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleCodeRequest::fromArray($request->get);
        } catch (ValidationException $e) {
            return null;
        }

        $code   = $dto->code;
        $permit = $this->storage->findByHash($code);
        if (! $permit instanceof Permit) {
            return null;
        }

        $this->renderer->render('admin_print_view', [
            'auth'            => $this->auth,
            'groupRepository' => $this->groupRepository,
            'holidayNotice'   => HolidayHtmlPresenter::formatHolidayNotice(
                $this->holidayService->getHolidaysInRange($permit->getValidFrom(), $permit->getValidUntil()),
            ),
            'opening_html' => HolidayHtmlPresenter::formatOpeningHours(
                $this->holidayService->getOpeningHoursDataForDateRange($permit->getValidFrom(), $permit->getValidUntil()),
            ),
            'permit'         => $permit,
            'userRepository' => $this->userRepository,
        ]);

        return null;
    }
}
