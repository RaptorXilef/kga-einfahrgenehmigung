<?php

declare(strict_types=1);

namespace App\Contracts\System;

/**
 * Interface zur Ermittlung der Client-IP-Adresse.
 * Entkoppelt die Application-Schicht von der Superglobalen $_SERVER.
 */
interface IpResolverInterface
{
    public function getIp(): string;
}
