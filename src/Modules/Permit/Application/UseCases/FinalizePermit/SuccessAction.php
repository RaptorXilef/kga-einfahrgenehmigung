<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\FinalizePermit;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Integration\FinanceIntegrationInterface;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeHandler;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeQuery;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\PermitReadDto;
use Override;

#[Route('GET', '/success')]
#[Route('POST', '/success')]
final readonly class SuccessAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private GetPermitByCodeHandler $getPermitByCodeHandler,
        private TemplateRenderer $renderer,
        private FinanceIntegrationInterface $financeIntegration,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $dto = SuccessRequest::fromArray($request->get);
        $permit = $this->getPermitByCodeHandler->handle(new GetPermitByCodeQuery($dto->code));

        if (!$permit instanceof PermitReadDto) {
            return new RedirectResponse('index');
        }

        $epcData = '';
        $usage = '';

        if ($dto->method === 'wire' && !$permit->isPaid) {
            $usage = $permit->usageText;
            $epcData = $this->financeIntegration->generateEpcQrData($permit->price, $usage);
        }

        $requirePayment = $this->config->getBool('require_payment_for_validity', false);

        $viewDto = new CheckoutSuccessViewDto(
            permitCode: $permit->code,
            method: $dto->method,
            isPaid: $permit->isPaid,
            requirePayment: $requirePayment,
            dueDate: $permit->paymentDueDateFormatted,
            epcData: $epcData,
            preisFormatted: $permit->priceFormatted,
            kontoinhaber: $this->config->getString('kontoinhaber'),
            iban: $this->config->getString('iban'),
            bic: $this->config->getString('bic'),
            usage: $usage,
            ownerEmail: $permit->ownerEmail !== '' ? $permit->ownerEmail : 'Ihre E-Mail-Adresse',
        );

        $html = $this->renderer->render('frontend/checkout_success', [
            'viewDto' => $viewDto,
        ]);

        return new HtmlResponse($html);
    }
}
