<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\BankImportProcessRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Core\Service\BankImportService;

final readonly class BankImportProcessAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private BankImportService $importService,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'dashboard.finance.bank_import';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = BankImportProcessRequest::fromArray($request->post);
            $res = $this->importService->processCsv($dto->tempFile, $dto->idColumn, $dto->amountColumn, $dto->dateColumn);

            // Temporäre Datei aufräumen
            if (\file_exists($dto->tempFile)) {
                @\unlink($dto->tempFile);
            }

            if (($res['success'] ?? false) === true) {
                $msg = "Bank-Abgleich beendet: <strong>{$res['erfolgreich']}</strong> Permits freigeschaltet, {$res['uebersprungen']} übersprungen (bereits bezahlt oder kein Match), {$res['fehlerhaft']} fehlerhaft/zu geringer Betrag.";

                return new RedirectResponse('admin.php?msg=' . \urlencode($msg));
            }

            return new RedirectResponse('admin.php?msg=' . \urlencode('Fehler bei der CSV-Verarbeitung.'));
        } catch (\Exception $e) {
            return new RedirectResponse('admin.php?msg=' . \urlencode($e->getMessage()));
        }
    }
}
