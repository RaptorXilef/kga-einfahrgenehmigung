<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SubmitPermitRequest;

/**
 * Mutabler State-Bag zum Sammeln des Ergebnisses eines Submit-Commands.
 * Erlaubt dem Handler strikt "void" zurückzugeben (CQRS).
 */
final class SubmitPermitContext
{
    public string $redirectAction = ''; // 'redirect_verify' oder 'redirect_checkout'

    public ?string $token = null;
}
