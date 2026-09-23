<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\VerifyMagicLink;

use App\Application\Session\SessionManager;
use App\Contracts\Security\RateLimiterInterface;
use App\Modules\Identity\Domain\MagicLink;
use App\Modules\Identity\Domain\MagicLinkRepositoryInterface;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use DateTimeImmutable;
use DomainException;

/**
 * @implements CommandHandlerInterface<VerifyMagicLinkCommand>
 */
final readonly class VerifyMagicLinkHandler implements CommandHandlerInterface
{
    public function __construct(
        private MagicLinkRepositoryInterface $repository,
        private SessionManager $sessionManager,
        private RateLimiterInterface $rateLimiter,
    ) {
    }

    /**
     * @param VerifyMagicLinkCommand $command
     */
    public function handle(mixed $command): void
    {
        $now = new DateTimeImmutable();

        // Passive Garbage Collection
        $this->repository->deleteExpired($now);

        $input = \strtoupper(\trim($command->input));
        $magicLink = $this->repository->findByInput($input);

        if (!$magicLink instanceof MagicLink || $magicLink->isExpired($now)) {
            $this->rateLimiter->recordFailedAttempt($command->ipAddress);

            throw new DomainException('Der Code oder Link ist ungültig oder abgelaufen.');
        }

        // Single-Use garantieren: Direkt nach erfolgreicher Identifikation löschen
        $this->repository->delete($magicLink->token);

        // Login vollziehen
        $this->rateLimiter->clearAttempts($command->ipAddress);
        $this->sessionManager->regenerate();
        $this->sessionManager->setHistoryEmail($magicLink->email->value);
    }
}
