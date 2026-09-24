<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetTemplatePrice;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Integration\VoucherIntegrationInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Modules\Permit\Domain\PermitFinancialCalculator;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use Throwable;

#[Route('POST', '/api/get_template_price')]
final readonly class GetTemplatePriceAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private RateLimiterInterface $rateLimiter,
        private VoucherIntegrationInterface $voucherIntegration,
        private PermitFinancialCalculator $financialCalculator,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $vehicleTypes = $this->config->get('vehicle_types', []);
            $defaultType = empty($vehicleTypes) ? 'pkw' : \array_key_first($vehicleTypes);

            $dto = ApiTemplatePriceRequest::fromArray($request->input, $defaultType);

            $templateKey = new TemplateKey($dto->key);
            $originalPrice = $this->financialCalculator->calculateBasePrice($templateKey, $dto->typ);
            $finalPrice = $originalPrice;
            $discountText = '';

            if ($dto->voucherCode !== '') {
                $discountResult = $this->voucherIntegration->calculateDiscount($dto->voucherCode, $originalPrice);

                if ($discountResult->isValid) {
                    $this->rateLimiter->clearAttempts($request->getIp());
                    $finalPrice = $discountResult->finalPrice;
                    $discountText = $discountResult->discountText;
                } else {
                    $this->rateLimiter->recordFailedAttempt($request->getIp());
                    $discountText = $discountResult->errorMessage;
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
