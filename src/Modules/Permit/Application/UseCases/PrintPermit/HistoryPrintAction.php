<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\PrintPermit;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\PdfStreamResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeHandler;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeQuery;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\PermitReadDto;
use App\SharedKernel\Application\Security\Sanitizer;
use Override;

#[Route('GET', '/history_print')]
#[Route('POST', '/history_print')]
final readonly class HistoryPrintAction implements ViewActionInterface
{
    public function __construct(
        private GetPermitByCodeHandler $getPermitByCodeHandler,
        private SessionManager $sessionManager,
        private GeneratePermitPdfHandler $generatePermitPdfHandler,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = PrintPermitRequest::fromArray($request->get);
        } catch (ValidationException) {
            return new RedirectResponse('history');
        }

        $code = $dto->code;
        $emailInSession = (string) $this->sessionManager->getHistoryEmail();

        $permit = $this->getPermitByCodeHandler->handle(new GetPermitByCodeQuery($code));

        if ($permit instanceof PermitReadDto && Sanitizer::normalizeEmail($permit->ownerEmail) === Sanitizer::normalizeEmail($emailInSession)) {
            $pdfBinary = $this->generatePermitPdfHandler->handle(new GeneratePermitPdfQuery($permit));

            return new PdfStreamResponse($pdfBinary, "Genehmigung_{$permit->code}.pdf");
        }

        return new RedirectResponse('history');
    }
}
