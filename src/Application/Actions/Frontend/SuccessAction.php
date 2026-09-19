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
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;
use App\Core\Entity\PermitStatus;
use App\Core\Service\BankQrGenerator;
use App\Core\Service\PermitService;

/**
 * Action für die Erfolgs- und Bestätigungsseite nach Abschluss eines Antrags.
 * Generiert bei Bedarf Bank-QR-Codes (EPC) für offene Überweisungen und zeigt
 * dem Benutzer die finalen Zahlungsanweisungen an.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[Route('GET', '/success')]
#[Route('POST', '/success')]
final readonly class SuccessAction implements ViewActionInterface
{
    public function __construct(
        private BankQrGenerator $bankQrGenerator,
        private ConfigInterface $config,
        private PermitService $permitService,
        private StorageInterface $storage,
        private TemplateRenderer $renderer,
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
        $code = $dto->code;
        $method = $dto->method;

        $permit = $this->storage->findByHash($code);
        if (!$permit instanceof Permit) {
            return new RedirectResponse('index');
        }

        $epcData = '';
        $usage = '';

        if ($method === 'wire' && $permit->getStatus() !== PermitStatus::Bezahlt) {
            $usage = $this->permitService->generateUsageText($permit);
            $epcData = $this->bankQrGenerator->generate($permit->getPrice(), $usage);
        }

        $requirePayment = (bool) $this->config->get('require_payment_for_validity', false);

        // Dynamisches Datum laden und formatieren
        $dueDate = $this->permitService->calculatePaymentDueDate($permit)->format('d.m.Y');

        $html = $this->renderer->render('frontend/checkout_success', [
            'dueDate' => $dueDate,
            'epcData' => \urlencode($epcData),
            'method' => $method,
            'permit' => $permit,
            'requirePayment' => $requirePayment,
            'usage' => $usage,
        ]);

        return new HtmlResponse($html);
    }
}
