<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\ExportRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\EmptyResponse;
use App\Application\Response\FileDownloadResponse;
use App\Application\Session\SessionManager;
use App\Modules\Finance\Application\UseCases\ExportFinanceData\ExportFinanceDataHandler;
use App\Modules\Finance\Application\UseCases\ExportFinanceData\ExportFinanceDataQuery;
use App\Modules\System\Application\Services\AuditLoggerService;

#[Route('GET', '/dashboard_export')]
#[Route('POST', '/dashboard_export')]
final readonly class DashboardExportAction implements ViewActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
        private ExportFinanceDataHandler $exportHandler, // CQRS
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'finance.export';
    }

    public function execute(ServerRequest $request): mixed
    {
        $sessionFilters = $this->sessionManager->getAdminFilters();
        $dto = ExportRequest::fromRequest($request, $sessionFilters);

        $query = new ExportFinanceDataQuery(
            $dto->format,
            $dto->start,
            $dto->end,
            $sessionFilters['type'] ?? 'all',
            $sessionFilters['q'] ?? '',
        );

        $result = $this->exportHandler->handle($query);

        $this->auditLogger->log('DATA_EXPORT', "Daten-Export ausgeführt. Format: {$dto->format}.");

        if ($result->content !== '') {
            return new FileDownloadResponse($result->content, $result->filename, $result->contentType);
        }

        return new EmptyResponse(400);
    }
}
g = 'Genehmigung wurde ' . $actionStr . '.';

            $this->auditLogger->log('PERMIT_SUSPENSION', "Genehmigung '{$dto->code}' wurde {$actionStr}. Grund: {$dto->reason}");
            $this->sessionManager->addFlash('success', $msg);

        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', 'Fehler: ' . $e->getMessage());
        }

        return new RedirectResponse('admin');
    }
}
RoleHandler->handle(new ChangeUserRoleCommand($userId, $roleId));

            $roles = $this->roleRepository->loadAll();
            $oldRoleName = isset($roles[$oldRole]) ? $roles[$oldRole]->name : $oldRole;
            $newRoleName = isset($roles[$roleId]) ? $roles[$roleId]->name : $roleId;

            $this->auditLogger->log(
                'USER_CHANGE_ROLE',
                "Rolle von Benutzer '{$username}' (ID: {$userId}) geändert: Von '{$oldRoleName}' auf '{$newRoleName}'.",
            );

            $this->sessionManager->addFlash('success', "Rolle für '{$username}' geändert.");

            return new RedirectResponse('users');

        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }
    }
}
 gewählten Genehmigungen konnte aktualisiert werden.');
        }

        return new RedirectResponse('admin');
    }
}
sessionManager->addFlash('error', 'Kritischer Fehler: ' . $e->getMessage());

            return new RedirectResponse('admin?focus=tab-tools');
        }
    }
}
