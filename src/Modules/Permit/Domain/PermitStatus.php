<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

enum PermitStatus: string
{
    case Offen = 'offen';
    case Bezahlt = 'bezahlt';
    case Storniert = 'storniert';
}
