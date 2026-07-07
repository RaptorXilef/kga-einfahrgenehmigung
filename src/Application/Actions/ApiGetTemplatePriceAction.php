<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\ApiTemplatePriceRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Core\Service\PermitService;
use App\Core\Service\VoucherService;

/**
 * Action für die dynamische Preisberechnung via API.
 * Evaluiert Vorlagen-Preise, Fahrzeugtypen und Gutscheincodes.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('get_template_price')]
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

    public function execute(ServerRequest $request): mixed
    {
        try {
            $vehicleTypes = $this->config->get('vehicle_types', []);
            $defaultType  = empty($vehicleTypes) ? 'pkw' : \array_key_first($vehicleTypes);

            $dto = ApiTemplatePriceRequest::fromArray($request->input, $defaultType);

            $templates     = $this->config->get('permit_templates', []);
            $template      = $templates[$dto->key] ?? $templates['std_7'];
            $originalPrice = (float) ($template['prices'][$dto->typ] ?? 0.0);

            $finalPrice   = $originalPrice;
            $discountText = '';

            if ($dto->voucherCode !== '') {
                $vouchers = $this->voucherRepo->loadAll();
                $v        = $vouchers[$dto->voucherCode] ?? null;

                if ($v && $this->voucherService->isValid($v)) {
                    $this->rateLimiter->clearAttempts($request->getIp());
                    $finalPrice   = $this->permitService->calculateDiscountedPrice($originalPrice, $v);
                    $discountText = match ($v->type) {
                        'fixed'   => 'Sonderpreis aktiviert',
                        'free'    => '100% Rabatt (Kostenlos)',
                        'percent' => $v->value . '% Rabatt',
                        default   => ''
                    };
                } else {
                    $this->rateLimiter->recordFailedAttempt($request->getIp());
                    $isDeactivated = $v ? $v->isDeactivated() : false;
                    $discountText  = $v ? ($isDeactivated ? 'Code gesperrt' : 'Code abgelaufen') : 'Ungültiger Code';
                }
            }

            return JsonResponse::success([
                'discountText' => $discountText,
                'formatted'    => \number_format($finalPrice, 2, ',', '.') . ' €',
                'isFree'       => $finalPrice <= 0,
                'original'     => $originalPrice,
                'price'        => $finalPrice,
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::sendPayload(
                ['error' => $e->getMessage(), 'formatted' => 'Fehler', 'price' => 0.0, 'success' => false],
                400,
            );
        }
    }
}
