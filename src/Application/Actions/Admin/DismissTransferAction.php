<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;

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

    public function getRequiredPermission(): string
    {
        return 'finance.mark_paid';
    }

    public function execute(ServerRequest $request): mixed
    {
        $id = (string) ($request->post['anomaly_id'] ?? '');

        if ($id !== '') {
            $this->sessionManager->removeCollectiveTransfer($id);
        }

        // Zurück ins Finanz-Tab
        return new RedirectResponse('admin?focus=tab-finance');
    }
}
