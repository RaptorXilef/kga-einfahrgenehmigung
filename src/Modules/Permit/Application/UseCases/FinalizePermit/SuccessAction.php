<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\FinalizePermit;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Modules\Finance\Application\UseCases\GenerateEpcQr\GenerateEpcQrHandler;
use App\Modules\Finance\Application\UseCases\GenerateEpcQr\GenerateEpcQrQuery;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeHandler;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeQuery;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitFinancialCalculator;
use App\Modules\Permit\Domain\PermitStatus;

#[Route('GET', '/success')]
#[Route('POST', '/success')]
final readonly class SuccessAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private GetPermitByCodeHandler $getPermitByCodeHandler,
        private PermitFinancialCalculator $financialCalculator,
        private TemplateRenderer $renderer,
        private GenerateEpcQrHandler $qrHandler,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $dto = SuccessRequest::fromArray($request->get);
        $permit = $this->getPermitByCodeHandler->handle(new GetPermitByCodeQuery($dto->code));

        if (!$permit instanceof Permit) {
            return new RedirectResponse('index');
        }

        $epcData = '';
        $usage = '';
        $isPaid = $permit->getStatus() === PermitStatus::Bezahlt;

        if ($dto->method === 'wire' && !$isPaid) {
            $usage = $this->financialCalculator->generateUsageText($permit);
            $epcData = $this->qrHandler->handle(new GenerateEpcQrQuery($permit->getPrice(), $usage));
        }

        $requirePayment = (bool) $this->config->get('require_payment_for_validity', false);
        $dueDate = $this->financialCalculator->calculatePaymentDueDate($permit)->format('d.m.Y');

        $viewDto = new CheckoutSuccessViewDto(
            permitCode: $permit->code->value,
            method: $dto->method,
            isPaid: $isPaid,
            requirePayment: $requirePayment,
            dueDate: $dueDate,
            epcData: $epcData,
            preisFormatted: \number_format($permit->getPrice(), 2, ',', '.') . ' €',
            kontoinhaber: (string) $this->config->get('kontoinhaber', ''),
            iban: (string) $this->config->get('iban', ''),
            bic: (string) $this->config->get('bic', ''),
            usage: $usage,
            ownerEmail: $permit->getOwnerEmail() ?: 'Ihre E-Mail-Adresse',
        );

        $html = $this->renderer->render('frontend/checkout_success', [
            'viewDto' => $viewDto,
        ]);

        return new HtmlResponse($html);
    }
}
