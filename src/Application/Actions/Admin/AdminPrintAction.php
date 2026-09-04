<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\SimpleCodeRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\View\HolidayHtmlPresenter;
use App\Application\View\TemplateRenderer;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\Permit;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\AuthService;
use App\Core\Service\HolidayService;
use App\Core\Service\PermitService;

#[Route('GET', '/admin_print')]
#[RequiresAuth]
final readonly class AdminPrintAction implements ViewActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
        private RoleRepositoryInterface $roleRepository,
        private HolidayService $holidayService,
        private PermitService $permitService,
        private TemplateRenderer $renderer,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleCodeRequest::fromArray($request->get);
        } catch (ValidationException) {
            return null;
        }

        $code = $dto->code;
        $permit = $this->permitService->resolvePermit($code);

        if (!$permit instanceof Permit) {
            return null;
        }

        $this->auditLogger->log('PERMIT_PRINT', "Druckvorschau für Genehmigung '{$code}' aufgerufen.");

        $this->renderer->render('admin/print_view', [
            'auth' => $this->auth,
            'roleRepository' => $this->roleRepository,
            'holidayNotice' => HolidayHtmlPresenter::formatHolidayNotice(
                $this->holidayService->getHolidaysInRange($permit->getValidFrom(), $permit->getValidUntil()),
            ),
            'opening_html' => HolidayHtmlPresenter::formatOpeningHours(
                $this->holidayService->getOpeningHoursDataForDateRange($permit->getValidFrom(), $permit->getValidUntil()),
            ),
            'permit' => $permit,
            'userRepository' => $this->userRepository,
        ]);

        return null;
    }
}
