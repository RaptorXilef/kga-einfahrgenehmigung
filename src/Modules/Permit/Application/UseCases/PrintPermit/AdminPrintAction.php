<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\PrintPermit;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\EmptyResponse;
use App\Application\Response\PdfStreamResponse;
use App\Contracts\System\AuditLoggerInterface;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeHandler;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\GetPermitByCodeQuery;
use App\Modules\Permit\Application\UseCases\GetPermitByCode\PermitReadDto;
use Override;

#[Route('GET', '/admin_print')]
#[RequiresAuth]
final readonly class AdminPrintAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private GetPermitByCodeHandler $getPermitByCodeHandler,
        private GeneratePermitPdfHandler $generatePermitPdfHandler,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'permits.print';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = PrintPermitRequest::fromArray($request->get);
        } catch (ValidationException) {
            return new EmptyResponse(400);
        }

        $code = $dto->code;
        $permit = $this->getPermitByCodeHandler->handle(new GetPermitByCodeQuery($code));

        if (!$permit instanceof PermitReadDto) {
            return new EmptyResponse(404);
        }

        $this->auditLogger->log('PERMIT_PRINT', "Druck-PDF für Genehmigung '{$code}' generiert.");

        $pdfBinary = $this->generatePermitPdfHandler->handle(new GeneratePermitPdfQuery($permit));

        return new PdfStreamResponse($pdfBinary, "Genehmigung_{$permit->code}.pdf");
    }
}
