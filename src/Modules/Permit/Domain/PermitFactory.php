<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Domain\ValueObject\PermitCode;
use App\SharedKernel\Domain\ValueObject\Price;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use DateTimeImmutable;

/**
 * Domain Factory zur Erstellung neuer Permit-Aggregate.
 * Kapselt die Geschäftsregeln für Code-Generierung, Gültigkeitsberechnung
 * und Standard-Zwecke.
 */
final readonly class PermitFactory
{
    public function __construct(
        private PermitRepositoryInterface $repository,
        private ConfigInterface $config,
        private ClockInterface $clock,
    ) {
    }

    public function createNew(
        TemplateKey $templateKey,
        Owner $owner,
        Vehicle $vehicle,
        DateTimeImmutable $startDate,
        ?DateTimeImmutable $customEndDate,
        Price $price,
        string $purpose,
        PermitStatus $status,
        ?string $internerKommentar,
        array $agreements,
    ): Permit {
        // 1. Calculate Validity End Date
        $templates = (array) $this->config->get('permit_templates', []);
        $template = $templates[$templateKey->value] ?? ['days' => 1];

        if (($template['days'] ?? 1) === 'custom') {
            $endDate = $customEndDate ?? $startDate;
        } else {
            $daysToAdd = \max(0, (int) ($template['days'] ?? 1) - 1);
            $endDate = $startDate->modify('+' . $daysToAdd . ' days');
        }

        // 2. Resolve Purpose
        $purposes = (array) $this->config->get('purposes', []);
        $zweck = $purposes[$purpose] ?? ($purpose !== '' ? \strip_tags($purpose) : 'Privat');

        // 3. Generate Unique Code
        $platePart = \str_replace(' ', '-', $vehicle->kennzeichen->value);
        if ($platePart === '' || $platePart === 'XXX-XX-9999') {
            $platePart = \strtoupper($vehicle->typ);
        }

        $useLongCode = (bool) $this->config->get('use_long_permit_code', false);
        $prefix = (string) $this->config->get('prefix', 'ML');

        do {
            $randomId = $this->generateV4Suffix();
            $fullIdentifier = $useLongCode
                ? \sprintf('%s-%s-%s-%s', $prefix, $owner->parzelle->getFormatted(), $platePart, $randomId)
                : $randomId;
        } while (!$this->repository->isCodeUnique($fullIdentifier));

        // 4. Return pure Aggregate Root
        return new Permit(
            code: new PermitCode($fullIdentifier),
            template_key: clone $templateKey,
            owner: clone $owner,
            vehicle: clone $vehicle,
            validity: new Validity($startDate, $endDate, clone $price, $zweck),
            status: new Status($status),
            erstellt: $this->clock->now(),
            interner_kommentar: $internerKommentar,
            agreements: $agreements,
        );
    }

    private function generateV4Suffix(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $res = '';
        for ($i = 0; $i < 8; ++$i) {
            $res .= $chars[\random_int(0, \strlen($chars) - 1)];
        }

        return $res;
    }
}
