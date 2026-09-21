<?php

declare(strict_types=1);

namespace App\SharedKernel\Application\Command;

/**
 * Interface für alle Command-Handler.
 * Jeder Handler ist exakt für ein Command zuständig (Single Responsibility).
 *
 * @template TCommand of CommandInterface
 */
interface CommandHandlerInterface
{
    /**
     * @param TCommand $command
     * @return void Commands geben nach striktem CQRS niemals Daten zurück (außer Exceptions bei Fehlern).
     */
    public function handle(CommandInterface $command): void;
}
