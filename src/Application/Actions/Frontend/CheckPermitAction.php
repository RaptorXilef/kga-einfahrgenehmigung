<?php

declare(strict_types=1);

namespace App\Application\Actions\Frontend;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\SimpleCodeRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Core\Service\AuthService;
use App\Modules\Permit\Application\UseCases\CheckPermit\GetPermitCheckDetailsHandler;
use App\Modules\Permit\Application\UseCases\CheckPermit\GetPermitCheckDetailsQuery;

/**
 * Action zur Überprüfung von Genehmigungen und Kennzeichen.
 * Erlaubt die öffentliche und administrative Abfrage von Gültigkeiten (z.B. via QR-Code).
 */
#[Route('GET', '/check')]
final readonly class CheckPermitAction implements ViewActionInterface
{
    public function __construct(
        private AuthService $auth,
        private ConfigInterface $config,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
        private GetPermitCheckDetailsHandler $checkHandler, // <-- CQRS Injected
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleCodeRequest::fromArray($request->get);
        } catch (ValidationException) {
            $html = $this->renderer->render('frontend/check_search');

            return new HtmlResponse($html);
        }

        // CQRS Query feuern - Die ganze Logik ist jetzt im Backend!
        $query = new GetPermitCheckDetailsQuery($dto->code, $this->auth->isLoggedIn(), $dto->token);
        $details = $this->checkHandler->handle($query);

        if (!$details->isFound) {
            $this->sessionManager->addFlash('error', "Code '{$dto->code}' nicht gefunden.");
            $html = $this->renderer->render('frontend/check_search');

            return new HtmlResponse($html);
        }

        // Standard-Daten für die Header-Navigation (falls eingeloggt)
        $adminData = [
            'adminUser' => $this->auth->getUsername(),
            'adminId' => $this->auth->getUserId(),
            'adminGroup' => $this->auth->getRole(),
        ];

        // Template rendern - Das PHTML benötigt jetzt nur noch das $details DTO
        $template = $details->showAdminView ? 'frontend/check_admin' : 'frontend/check_public';

        $html = $this->renderer->render($template, \array_merge($adminData, [
            'auth' => $this->auth,
            'details' => $details, // <-- DTO Übergabe
        ]));

        return new HtmlResponse($html);
    }
}
