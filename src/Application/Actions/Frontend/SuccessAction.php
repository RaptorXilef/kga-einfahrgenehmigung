<?php

declare(strict_types=1);

namespace App\Application\Actions\Frontend;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\SuccessRequest;
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

/**
 * Action für die Erfolgs- und Bestätigungsseite nach Abschluss eines Antrags.
 * Generiert bei Bedarf Bank-QR-Codes (EPC) für offene Überweisungen und zeigt
 * dem Benutzer die finalen Zahlungsanweisungen an.
 */
#[Route('GET', '/success')]
#[Route('POST', '/success')]
final readonly class SuccessAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private GetPermitByCodeHandler $getPermitByCodeHandler, // CQRS
        private PermitFinancialCalculator $financialCalculator, // Domain Service
        private TemplateRenderer $renderer,
        private GenerateEpcQrHandler $qrHandler, // CQRS
    ) {
    }

    /**
     * TODO DOCBLOCK
     * Haupt-Request-Handler für die Success-Seite.
     * Validiert das Ticket und bereitet die Bezahlinformationen auf.
     */
    public function execute(ServerRequest $request): mixed
    {
        $dto = SuccessRequest::fromArray($request->get);
        $permit = $this->getPermitByCodeHandler->handle(new GetPermitByCodeQuery($dto->code));

        if (!$permit instanceof Permit) {
            return new RedirectResponse('index');
        }

        $epcData = '';
        $usage = '';

        if ($dto->method === 'wire' && $permit->getStatus() !== PermitStatus::Bezahlt) {
            $usage = $this->financialCalculator->generateUsageText($permit);
            $epcData = $this->qrHandler->handle(new GenerateEpcQrQuery($permit->getPrice(), $usage));
        }

        $requirePayment = (bool) $this->config->get('require_payment_for_validity', false);

        // Dynamisches Datum laden und formatieren
        $dueDate = $this->financialCalculator->calculatePaymentDueDate($permit)->format('d.m.Y');

        $html = $this->renderer->render('frontend/checkout_success', [
            'dueDate' => $dueDate,
            'epcData' => \urlencode($epcData),
            'method' => $dto->method,
            'permit' => $permit,
            'requirePayment' => $requirePayment,
            'usage' => $usage,
        ]);

        return new HtmlResponse($html);
    }
}
