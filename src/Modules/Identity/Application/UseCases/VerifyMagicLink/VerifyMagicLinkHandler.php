<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\VerifyMagicLink;

use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Identity\Domain\MagicLink;
use App\Modules\Identity\Domain\MagicLinkRepositoryInterface;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use App\SharedKernel\Application\Command\CommandInterface;
use DomainException;
use Override;

/**
 * @implements CommandHandlerInterface<VerifyMagicLinkCommand>
 */
final readonly class VerifyMagicLinkHandler implements CommandHandlerInterface
{
    public function __construct(
        private MagicLinkRepositoryInterface $repository,
        private AuthSessionInterface $session,
        private RateLimiterInterface $rateLimiter,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param VerifyMagicLinkCommand $command
     */
    #[Override]
    public function handle(CommandInterface $command): void
    {
        $now = $this->clock->now();

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
        $this->session->regenerate();
        $this->session->setHistoryEmail($magicLink->email->value);
    }
}
