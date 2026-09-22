<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain\Exceptions;

use DomainException;

final class PermitCollisionException extends DomainException
{
    // Diese Exception dient nur als eindeutiger Typ (Marker) für die Steuerung der Use-Cases.
}
