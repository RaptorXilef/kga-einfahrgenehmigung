<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageProfile;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Security\AuthorizationInterface;
use App\Contracts\System\AuditLoggerInterface;
use App\Contracts\System\ImageStorageInterface;
use Override;

#[Route('POST', '/change_own_avatar')]
#[RequiresAuth]
final readonly class ProfileUploadAvatarAction implements ActionInterface
{
    public function __construct(
        private AuthorizationInterface $auth,
        private ImageStorageInterface $imageStorage,
        private SessionManager $sessionManager,
        private AuditLoggerInterface $auditLogger,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $userId = $this->auth->getUserId();

        if (\str_starts_with($userId, 'sys_')) {
            $this->sessionManager->addFlash('error', 'System-Accounts können nicht bearbeitet werden.');

            return new RedirectResponse('admin');
        }

        try {
            $dto = ProfileUploadAvatarRequest::fromFiles($request->files);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('profile');
        }

        if ($this->imageStorage->uploadImage('user', $userId, $dto->file)) {
            $this->auditLogger->log('PROFILE_AVATAR_UPLOAD', 'Eigenes Profilbild aktualisiert.');
            $this->sessionManager->addFlash('success', 'Erfolg: Profilbild wurde aktualisiert.');
        } else {
            $this->sessionManager->addFlash('error', 'Fehler bei der Bildverarbeitung.');
        }

        return new RedirectResponse('profile');
    }
}
