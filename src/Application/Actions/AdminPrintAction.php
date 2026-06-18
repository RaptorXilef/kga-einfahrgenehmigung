<?php

declare(strict_types=1);

namespace App\Application\Actions;

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
 * Path: src/Application/Actions/AdminPrintAction.php
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
        $code = (string) ($requestData['code'] ?? '');
        if ($code === '') {
            return;
        }

        $permit = $this->storage->findByHash($code);
        if (! $permit instanceof Permit) {
            return;
        }

        $now       = new \DateTimeImmutable('today');
        $isExpired = $permit->getValidUntil() < $now;
        $isFuture  = $permit->getValidFrom() > $now;

        $hasRight = false;
        if ($this->auth->hasPermission('check.admin.print')) {
            $hasRight = true;
        } elseif ($isExpired && $this->auth->hasPermission('dashboard.expired.print')) {
            $hasRight = true;
        } elseif ($isFuture && $this->auth->hasPermission('dashboard.future.print')) {
            $hasRight = true;
        } elseif (! $isExpired && ! $isFuture && $this->auth->hasPermission('dashboard.active.print')) {
            $hasRight = true;
        }

        if (! $hasRight) {
            exit('Fehler: Sie haben keine Berechtigung, Genehmigungen in diesem spezifischen Status zu drucken.');
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
