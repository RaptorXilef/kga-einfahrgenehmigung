<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Core\Service\VoucherService;

/**
 * Action zum Rendern des öffentlichen Antragsformulars.
 *
 * Path: src/Application/Actions/PermitRenderAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PermitRenderAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private TemplateRenderer $renderer,
        private VoucherRepositoryInterface $voucherRepository,
        private VoucherService $voucherService,
    ) {
    }

    // TODO DOCBLOCK
    public function execute(array $requestData): void
    {
        $get = $requestData['get'];

        $message = (string) ($get['msg'] ?? '');
        $success = false;

        // Dynamische Bestätigungsnachricht abfangen
        if (isset($get['sent'])) {
            $success = true;
            $message = $get['msg'] ?? 'Bestätigung erforderlich! Wir haben Ihnen eine E-Mail gesendet. Bitte klicken Sie auf den Link darin, um Ihren Antrag zu aktivieren.';
        }

        $this->renderer->render('formular', [
            'agreements'        => $this->getParsedAgreements(),
            'formData'          => $_SESSION['form_data'] ?? [],
            'hasActiveVouchers' => $this->checkAvailableVouchers(),
            'message'           => $message,
            'success'           => $success,
        ]);
    }

    // TODO DOCBLOCK
    private function checkAvailableVouchers(): bool
    {
        $vouchers = $this->voucherRepository->loadAll();
        foreach ($vouchers as $v) {
            if ($this->voucherService->isValid($v)) {
                return true;
            }
        }

        return false;
    }

    // TODO DOCBLOCK
    private function getParsedAgreements(): array
    {
        $agreementsConfig = $this->config->get('agreements', []);
        $baseUrl          = $this->config->getBaseUrl() ?? '/';
        $parsed           = [];

        foreach ($agreementsConfig as $key => $agree) {
            $cleanLabel = \htmlspecialchars($agree['label']);

            if (! empty($agree['link'])) {
                if (\filter_var($agree['link'], \FILTER_VALIDATE_URL)) {
                    $finalLink = $agree['link'];
                } else {
                    $finalLink = \rtrim($baseUrl, '/') . '/' . \ltrim($agree['link'], '/');
                }

                $linkHtml = '<a href="' . \htmlspecialchars($finalLink) .
                    '" target="_blank" style="color: var(--primary-color); text-decoration: underline; font-weight: 500;">$1</a>';
                $renderedLabel = \preg_replace('/\[(.*?)\]/', $linkHtml, $cleanLabel);
            } else {
                $renderedLabel = \preg_replace('/\[(.*?)\]/', '$1', $cleanLabel);
            }

            $parsed[$key] = [
                'label_html' => $renderedLabel,
                'required'   => $agree['required'] ?? false,
            ];
        }

        return $parsed;
    }
}
