<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ProcessBankImport;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use Throwable;

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

    public function getRequiredPermission(): string
    {
        return 'finance.bank_import';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = BankImportProcessRequest::fromArray($request->post);

            $result = $this->processHandler->handle(new ProcessBankImportCommand(
                $dto->tempFile,
                $dto->idColumn,
                $dto->amountColumn,
                $dto->dateColumn,
            ));

            if ($result->success) {
                foreach ($result->collectiveTransfers as $transfer) {
                    $this->sessionManager->addCollectiveTransfer($transfer);
                }

                $baseUrl = \rtrim($this->config->getBaseUrl(), '/') . '/';

                $formatList = function (array $categories): string {
                    $html = '<ul class="u-margin-block-xs u-padding-inline-start-m">';
                    foreach ($categories as $cat => $items) {
                        if (\is_numeric($cat)) {
                            $html .= '<li>' . \htmlspecialchars((string) $items) . '</li>';
                        } else {
                            $html .= '<li class="u-margin-block-end-xs"><strong class="u-font-bold"><em>' . \htmlspecialchars($cat) . '</em></strong>:';
                            $html .= '<ul class="u-margin-block-start-none u-margin-block-end-xs u-padding-inline-start-m">';
                            foreach ((array) $items as $item) {
                                $html .= '<li>' . \htmlspecialchars((string) $item) . '</li>';
                            }
                            $html .= '</ul></li>';
                        }
                    }

                    return $html . '</ul>';
                };

                $htmlDetails = [];
                if ($result->successDetails !== []) {
                    $htmlDetails[] = '<div class="u-margin-bottom-s"><img src="' . $baseUrl . 'assets/img/icons/success.webp" class="c-icon c-icon--inline" alt="" loading="lazy"> <strong>Freigeschaltet:</strong>' . $formatList($result->successDetails) . '</div>';
                }
                if ($result->skippedDetails !== []) {
                    $htmlDetails[] = '<div class="u-margin-bottom-s"><img src="' . $baseUrl . 'assets/img/icons/skip.webp" class="c-icon c-icon--inline" alt="" loading="lazy"> <strong>Übersprungen:</strong>' . $formatList($result->skippedDetails) . '</div>';
                }
                if ($result->errorDetails !== []) {
                    $htmlDetails[] = '<div class="u-margin-bottom-s"><img src="' . $baseUrl . 'assets/img/icons/warning.webp" class="c-icon c-icon--inline" alt="" loading="lazy"> <strong>Fehlerhaft / Prüfen:</strong>' . $formatList($result->errorDetails) . '</div>';
                }

                $msg = "<div class=\"u-margin-bottom-m\">Bank-Abgleich beendet: <strong>{$result->successCount}</strong> Permits freigeschaltet, {$result->skippedCount} übersprungen, {$result->errorCount} fehlerhaft.</div>";
                $fullMsg = $msg . \implode('', $htmlDetails);

                $this->sessionManager->addFlash('success', $fullMsg);
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
