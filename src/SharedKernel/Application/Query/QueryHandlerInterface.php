<?php

declare(strict_types=1);

namespace App\SharedKernel\Application\Query;

/**
 * Interface für alle Query-Handler.
 * Liefert flache, stark typisierte Read-Models (DTOs) zurück - niemals Entities!
 *
 * @template TQuery of QueryInterface
 * @template TResult
 */
interface QueryHandlerInterface
{
    /**
     * @param TQuery $query
     *
     * @return TResult
     */
    public function handle(QueryInterface $query): mixed;
}
