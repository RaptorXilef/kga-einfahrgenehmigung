<?php

declare(strict_types=1);

namespace App\Core\Exception;

use DomainException;

final class PermitCollisionException extends DomainException
{
    // Diese Exception dient nur als eindeutiger Typ (Marker)
}
