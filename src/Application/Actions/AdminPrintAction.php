<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleCodeRequest;
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
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
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

    public function execute(array $requestData): void
    {
        $dto  = SimpleCodeRequest::fromArray($requestData['get'] ?? []);
        $code = $dto->code;

        if ($code === '') {
            return;
        }

        $permit = $this->storage->findByHash($code);
        if (! $permit instanceof Permit) {
            return;
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
    }
}
