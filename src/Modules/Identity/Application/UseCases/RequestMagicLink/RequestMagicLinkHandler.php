<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\RequestMagicLink;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Core\Event\MagicLinkRequestedEvent;
use App\Modules\Identity\Domain\MagicLink;
use App\Modules\Identity\Domain\MagicLinkRepositoryInterface;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use DateTimeImmutable;

/**
 * @implements CommandHandlerInterface<RequestMagicLinkCommand>
 */
final readonly class RequestMagicLinkHandler implements CommandHandlerInterface
{
    public function __construct(
        private MagicLinkRepositoryInterface $repository,
        private EventDispatcherInterface $eventDispatcher,
        private ConfigInterface $config,
    ) {
    }

    /**
     * @param RequestMagicLinkCommand $command
     */
    public function handle(mixed $command): void
    {
        $token = \bin2hex(\random_bytes(32));
        $code = \strtoupper(\substr(\bin2hex(\random_bytes(4)), 0, 6));

        $duration = (int) $this->config->get('magic_link_duration', 15);
        $expiresAt = (new DateTimeImmutable())->modify("+{$duration} minutes");

        $magicLink = new MagicLink(
            $token,
            new EmailAddress($command->email),
            $code,
            $expiresAt,
        );

        $this->repository->save($magicLink);

        // Event feuern, damit der Mail-Listener die asynchrone Arbeit übernimmt
        $this->eventDispatcher->dispatch(new MagicLinkRequestedEvent(
            $command->email,
            $token,
            $code,
        ));
    }
}
