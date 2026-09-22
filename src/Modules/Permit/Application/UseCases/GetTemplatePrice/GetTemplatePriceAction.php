<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetTemplatePrice;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Modules\Voucher\Application\UseCases\CalculateVoucherDiscount\CalculateVoucherDiscountHandler;
use App\Modules\Voucher\Application\UseCases\CalculateVoucherDiscount\CalculateVoucherDiscountQuery;
use Throwable;

#[Route('POST', '/api/get_template_price')]
final readonly class GetTemplatePriceAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private RateLimiterInterface $rateLimiter,
        private CalculateVoucherDiscountHandler $discountHandler,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $vehicleTypes = $this->config->get('vehicle_types', []);
            $defaultType = empty($vehicleTypes) ? 'pkw' : \array_key_first($vehicleTypes);

            $dto = ApiTemplatePriceRequest::fromArray($request->input, $defaultType);

            $templates = $this->config->get('permit_templates', []);
            $template = $templates[$dto->key] ?? $templates['std_7'];

            $originalPrice = (float) ($template['prices'][$dto->typ] ?? 0.0);
            $finalPrice = $originalPrice;
            $discountText = '';

            if ($dto->voucherCode !== '') {
                $query = new CalculateVoucherDiscountQuery($dto->voucherCode, $originalPrice);
                $discountDto = $this->discountHandler->handle($query);

                if ($discountDto->isValid) {
                    $this->rateLimiter->clearAttempts($request->getIp());
                    $finalPrice = $discountDto->finalPrice;
                    $discountText = $discountDto->discountText;
                } else {
                    $this->rateLimiter->recordFailedAttempt($request->getIp());
                    $discountText = $discountDto->errorMessage;
                }
            }

            return JsonResponse::success([
                'discountText' => $discountText,
                'formatted' => \number_format($finalPrice, 2, ',', '.') . ' €',
                'isFree' => $finalPrice <= 0.001,
                'original' => $originalPrice,
                'price' => $finalPrice,
            ]);
        } catch (Throwable $e) {
            return JsonResponse::sendPayload([
                'error' => $e->getMessage(),
                'formatted' => 'Fehler',
                'price' => 0.0,
                'success' => false,
            ], 400);
        }
    }
}
