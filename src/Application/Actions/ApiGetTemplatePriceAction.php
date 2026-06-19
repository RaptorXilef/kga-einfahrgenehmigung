<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ApiTemplatePriceRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Core\Service\PermitService;
use App\Core\Service\VoucherService;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ApiGetTemplatePriceAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private PermitService $permitService,
        private RateLimiterInterface $rateLimiter,
        private VoucherRepositoryInterface $voucherRepo,
        private VoucherService $voucherService,
    ) {
    }

    public function execute(array $requestData): mixed
    {
        try {
            $vehicleTypes = $this->config->get('vehicle_types', []);
            $defaultType  = empty($vehicleTypes) ? 'pkw' : \array_key_first($vehicleTypes);

            $dto = ApiTemplatePriceRequest::fromArray($requestData['input'], $defaultType);

            $templates     = $this->config->get('permit_templates', []);
            $template      = $templates[$dto->key] ?? $templates['std_7'];
            $originalPrice = (float) ($template['prices'][$dto->typ] ?? 0.0);

            $finalPrice   = $originalPrice;
            $discountText = '';

            if ($dto->voucherCode !== '') {
                $vouchers = $this->voucherRepo->loadAll();
                $v        = $vouchers[$dto->voucherCode] ?? null;

                if ($v && $this->voucherService->isValid($v)) {
                    $this->rateLimiter->clearAttempts($requestData['ip']);
                    $finalPrice = $this->permitService->calculateDiscountedPrice($originalPrice, $v);

                    $discountText = match ($v['type']) {
                        'fixed'   => 'Sonderpreis aktiviert',
                        'free'    => '100% Rabatt (Kostenlos)',
                        'percent' => (float) $v['value'] . '% Rabatt',
                        default   => ''
                    };
                } else {
                    $this->rateLimiter->recordFailedAttempt($requestData['ip']);
                    $isDeactivated = ($v['status'] ?? 'aktiv') === 'deaktiviert';
                    $discountText  = $v ? ($isDeactivated ? 'Code gesperrt' : 'Code abgelaufen') : 'Ungültiger Code';
                }
            }

            JsonResponse::success([
                'discountText' => $discountText,
                'formatted'    => \number_format($finalPrice, 2, ',', '.') . ' €',
                'isFree'       => $finalPrice <= 0,
                'original'     => $originalPrice,
                'price'        => $finalPrice,
            ]);
        } catch (\Throwable $e) {
            JsonResponse::send(
                ['error' => $e->getMessage(), 'formatted' => 'Fehler', 'price' => 0.0, 'success' => false],
                400,
            );
        }

        return null;
    }
}
