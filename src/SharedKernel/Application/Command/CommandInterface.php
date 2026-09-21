<?php

declare(strict_types=1);

namespace App\SharedKernel\Application\Command;

/**
 * Marker-Interface für alle Commands im System (Schreibende Use-Cases).
 * Ein Command transportiert ausschließlich Daten (DTO) und enthält keine Logik.
 */
interface CommandInterface
{
}
