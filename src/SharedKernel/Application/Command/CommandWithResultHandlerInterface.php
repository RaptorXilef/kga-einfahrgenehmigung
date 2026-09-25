<?php

declare(strict_types=1);

namespace App\SharedKernel\Application\Command;

/**
 * Interface für pragmatische Command-Handler, die einen generierten Identifier (string/int)
 * oder ein Prozess-Result-DTO zurückgeben müssen.
 *
 * @template TCommand of CommandInterface
 * @template TResult
 */
interface CommandWithResultHandlerInterface
{
    /**
     * @param TCommand $command
     *
     * @return TResult
     */
    public function handle(CommandInterface $command): mixed;
}
