<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ProcessBankImport;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Modules\Finance\Presentation\View\BankImportReportPresenter;
use Override;
use Throwable;

/**
 * Action zur Durchführung des CSV-Bankabgleichs (VSA).
 */
#[Route('POST', '/bank_import_process')]
#[RequiresAuth]
final readonly class ProcessBankImportAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private SessionManager $sessionManager,
        private ConfigInterface $config,
        private ProcessBankImportHandler $processHandler,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'finance.bank_import';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = BankImportProcessRequest::fromArray($request->post);

            $command = new ProcessBankImportCommand(
                $dto->tempFile,
                $dto->idColumn,
                $dto->amountColumn,
                $dto->dateColumn,
            );

            $result = $this->processHandler->handle($command);

            if ($result->success) {
                foreach ($result->collectiveTransfers as $transfer) {
                    $this->sessionManager->addCollectiveTransfer($transfer);
                }

                $reportHtml = BankImportReportPresenter::formatFlashReport($result, $this->config->getBaseUrl());
                $this->sessionManager->addFlash('success', $reportHtml);
            } else {
                $this->sessionManager->addFlash('error', $result->message);
            }

            return new RedirectResponse('admin?focus=tab-finance');
        } catch (Throwable $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin?focus=tab-finance');
        }
    }
}
