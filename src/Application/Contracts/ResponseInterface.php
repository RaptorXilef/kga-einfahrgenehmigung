<?php

declare(strict_types=1);

namespace App\Application\Contracts;

/**
 * TODO DOCBLOCK
 */
interface ResponseInterface
{
    public function send(): void;
}
