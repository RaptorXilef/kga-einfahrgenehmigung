<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\UseCases\ProcessBankImport;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use Override;

/**
 * Action zum Ausblenden/Erledigen einer manuell geprüften Sammelüberweisung.
 */
#[Route('POST', '/dismiss_transfer')]
final readonly class DismissTransferAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private SessionManager $sessionManager,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'finance.mark_paid';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $id = (string) ($request->post['anomaly_id'] ?? '');

        if ($id !== '') {
            $this->sessionManager->removeCollectiveTransfer($id);
        }

        return new RedirectResponse('admin?focus=tab-finance');
    }
}
