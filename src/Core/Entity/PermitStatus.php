<?php

declare(strict_types=1);

namespace App\Core\Entity;

enum PermitStatus: string
{
    case Offen     = 'offen';
    case Bezahlt   = 'bezahlt';
    case Storniert = 'storniert';
}
