<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageMails;

use App\Contracts\Mail\MailLogEntry;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\SharedKernel\Application\Command\CommandInterface;
use App\SharedKernel\Application\Command\CommandWithResultHandlerInterface;
use DomainException;
use Override;

/**
 * Sucht den Log-Eintrag speicherschonend per Einzelabfrage und reiht die E-Mail mit höchster Priorität neu ein.
 *
 * @implements CommandWithResultHandlerInterface<ResendMailCommand, ResendMailResult>
 */
final readonly class ResendMailHandler implements CommandWithResultHandlerInterface
{
    public function __construct(
        private MailLogInterface $mailLog,
        private MailServiceInterface $mailService,
    ) {
    }

    /**
     * @param ResendMailCommand $command
     */
    #[Override]
    public function handle(CommandInterface $command): ResendMailResult
    {
        $log = $this->mailLog->findByTimestamp($command->timestamp);

        if (!$log instanceof MailLogEntry) {
            throw new DomainException('Fehler: Log-Eintrag nicht gefunden.');
        }

        if ($log->data === []) {
            throw new DomainException('Fehler: Alter Log-Eintrag (Keine Rohdaten für Neuversand vorhanden).');
        }

        $this->mailService->sendTemplate(
            $log->recipient,
            $log->subject,
            $log->template->value,
            $log->data,
            $log->replyTo,
            100, // Manuell angestoßene Mails sollten sofort rausgehen
        );

        return new ResendMailResult(
            recipient: $log->recipient,
            subject: $log->subject,
        );
    }
}
