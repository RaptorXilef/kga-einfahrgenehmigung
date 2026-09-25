<?php

declare(strict_types=1);

namespace App\Modules\Permit\Infrastructure;

use App\Modules\Permit\Domain\Owner;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitStatus;
use App\Modules\Permit\Domain\Status;
use App\Modules\Permit\Domain\Validity;
use App\Modules\Permit\Domain\Vehicle;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PermitCode;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use App\SharedKernel\Domain\ValueObject\Price;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use DateTimeImmutable;
use Exception;

/**
 * Trait für die bidirektionale Transformation zwischen Permit-Entitäten und relationalen Arrays.
 * VSA FIX: Nutzt zu 100% das injizierte ClockInterface für alle Datums-Fallbacks.
 */
trait PermitMapperTrait
{
    /**
     * @param array<string, mixed> $item
     */
    public function mapToEntity(array $item): Permit
    {
        $now = $this->clock->now();

        $tKeyStr = \trim((string) ($item['template_key'] ?? 'std_7'));
        if ($tKeyStr === '') {
            $tKeyStr = 'std_7';
        }

        $emailStr = \trim((string) ($item['email'] ?? ''));
        if ($emailStr === '[ANONYMISIERT]' || $emailStr === 'ANONYMISIERT') {
            $emailStr = '';
        }
        $emailObj = $emailStr !== '' && $emailStr !== '0' ? new EmailAddress($emailStr) : null;

        $pzInt = (int) ($item['parzelle'] ?? 0);

        $kzStr = \trim((string) ($item['kennzeichen'] ?? ''));
        if (\in_array($kzStr, ['', '[ANONYMISIERT]', 'ANONYMISIERT'], true)) {
            $kzStr = 'XXX-XX 9999';
        }

        $codeStr = \trim((string) ($item['code'] ?? ''));
        if ($codeStr === '') {
            $codeStr = 'LEGACY-' . \strtoupper(\bin2hex(\random_bytes(4)));
        }

        $name = (string) ($item['name'] ?? 'Unbekannt');
        $von = \trim((string) ($item['von'] ?? ''));
        $bis = \trim((string) ($item['bis'] ?? ''));
        $created = \trim((string) ($item['erstellt'] ?? ''));

        $is_suspended = (bool) ($item['is_suspended'] ?? false);
        $suspReason = isset($item['suspension_reason']) ? (string) $item['suspension_reason'] : null;
        $kommentar = isset($item['interner_kommentar']) ? (string) $item['interner_kommentar'] : null;

        try {
            $dtVon = $von !== '' ? new DateTimeImmutable($von) : $now->setTime(0, 0, 0);
        } catch (Exception) {
            $dtVon = $now->setTime(0, 0, 0);
        }

        try {
            $dtBis = $bis !== '' ? new DateTimeImmutable($bis) : $now->modify('+1 day')->setTime(0, 0, 0);
        } catch (Exception) {
            $dtBis = $now->modify('+1 day')->setTime(0, 0, 0);
        }

        try {
            $dtCreated = $created !== '' ? new DateTimeImmutable($created) : $now;
        } catch (Exception) {
            $dtCreated = $now;
        }

        $bezahltAmStr = isset($item['bezahlt_am']) ? (string) $item['bezahlt_am'] : null;
        $dtBezahltAm = null;
        if (!\in_array($bezahltAmStr, [null, '', '0000-00-00 00:00:00', 'null'], true)) {
            try {
                $dtBezahltAm = new DateTimeImmutable($bezahltAmStr);
            } catch (Exception) {
            }
        }

        $agreements = $item['agreements'] ?? [];
        if (\is_string($agreements) && \property_exists($this, 'jsonHelper')) {
            $agreements = $this->jsonHelper->decode($agreements);
        }

        $statusEnum = PermitStatus::tryFrom((string) ($item['status'] ?? 'offen')) ?? PermitStatus::Offen;

        $lastReminderStr = isset($item['last_reminder_at']) ? (string) $item['last_reminder_at'] : null;
        $dtLastReminder = null;
        if (!\in_array($lastReminderStr, [null, '', '0000-00-00 00:00:00', 'null'], true)) {
            try {
                $dtLastReminder = new DateTimeImmutable($lastReminderStr);
            } catch (Exception) {
            }
        }

        return new Permit(
            code: clone new PermitCode($codeStr),
            template_key: clone new TemplateKey($tKeyStr),
            owner: new Owner($name, $emailObj, clone new PlotNumber($pzInt)),
            vehicle: new Vehicle((string) ($item['typ'] ?? 'pkw'), clone new LicensePlate($kzStr), isset($item['firma']) ? (string) $item['firma'] : null),
            validity: new Validity($dtVon, $dtBis, new Price((float) ($item['preis'] ?? 0.0)), (string) ($item['zweck'] ?? 'Privat')),
            status: new Status($statusEnum, $is_suspended, $suspReason, $dtLastReminder),
            erstellt: $dtCreated,
            interner_kommentar: $kommentar,
            agreements: \is_array($agreements) ? $agreements : [],
            bezahlt_am: $dtBezahltAm,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function flattenEntity(Permit $permit): array
    {
        return [
            'agreements' => \is_array($permit->agreements) ? \json_encode($permit->agreements, \JSON_UNESCAPED_UNICODE) : '{}',
            'bezahlt_am' => $permit->getPaidAt() instanceof DateTimeImmutable ? $permit->getPaidAt()->format('Y-m-d H:i:s') : null,
            'bis' => $permit->getValidUntil()->format('Y-m-d'),
            'code' => $permit->code->value,
            'email' => $permit->owner->email instanceof EmailAddress ? $permit->owner->email->value : '',
            'erstellt' => $permit->getCreatedAt()->format('Y-m-d H:i:s'),
            'firma' => $permit->getCompany(),
            'interner_kommentar' => $permit->getInternalComment(),
            'is_suspended' => (int) $permit->isSuspended(),
            'kennzeichen' => $permit->vehicle->kennzeichen->value,
            'name' => $permit->getOwnerName(),
            'parzelle' => $permit->owner->parzelle->value,
            'preis' => $permit->validity->preis->amount,
            'last_reminder_at' => $permit->getStatusObject()->last_reminder_at instanceof DateTimeImmutable ? $permit->getStatusObject()->last_reminder_at->format('Y-m-d H:i:s') : null,
            'status' => $permit->getStatus()->value,
            'suspension_reason' => $permit->getSuspensionReason(),
            'template_key' => $permit->template_key->value,
            'typ' => $permit->vehicle->typ,
            'von' => $permit->getValidFrom()->format('Y-m-d'),
            'zweck' => $permit->getPurpose(),
        ];
    }
}
