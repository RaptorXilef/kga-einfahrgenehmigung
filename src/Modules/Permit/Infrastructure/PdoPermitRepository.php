<?php

declare(strict_types=1);

namespace App\Modules\Permit\Infrastructure;

use App\Modules\Permit\Domain\Owner;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
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
use PDO;

final readonly class PdoPermitRepository implements PermitRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function save(Permit $permit): void
    {
        $sql = 'INSERT INTO permits (
            code, template_key, name, email, kennzeichen, parzelle, typ, firma, zweck,
            preis, von, bis, status, is_suspended, suspension_reason, erstellt,
            interner_kommentar, agreements, bezahlt_am, last_reminder_at
        ) VALUES (
            :code, :tpl, :name, :email, :plate, :plot, :typ, :firma, :zweck,
            :preis, :von, :bis, :status, :sus, :susr, :erst,
            :ik, :agr, :bez, :rem
        ) ON DUPLICATE KEY UPDATE
            template_key=VALUES(template_key), name=VALUES(name), email=VALUES(email),
            kennzeichen=VALUES(kennzeichen), parzelle=VALUES(parzelle), typ=VALUES(typ),
            firma=VALUES(firma), zweck=VALUES(zweck), preis=VALUES(preis), von=VALUES(von),
            bis=VALUES(bis), status=VALUES(status), is_suspended=VALUES(is_suspended),
            suspension_reason=VALUES(suspension_reason), interner_kommentar=VALUES(interner_kommentar),
            bezahlt_am=VALUES(bezahlt_am), last_reminder_at=VALUES(last_reminder_at)';

        $this->pdo->prepare($sql)->execute([
            'code' => $permit->code->value,
            'tpl' => $permit->template_key->value,
            'name' => $permit->owner->name,
            'email' => $permit->owner->email?->value,
            'plate' => $permit->vehicle->kennzeichen->value,
            'plot' => $permit->owner->parzelle->value,
            'typ' => $permit->vehicle->typ,
            'firma' => $permit->vehicle->firma,
            'zweck' => $permit->validity->zweck,
            'preis' => $permit->validity->preis->value,
            'von' => $permit->validity->von->format('Y-m-d'),
            'bis' => $permit->validity->bis->format('Y-m-d'),
            'status' => $permit->getStatus()->value,
            'sus' => (int) $permit->isSuspended(),
            'susr' => $permit->getSuspensionReason(),
            'erst' => $permit->erstellt->format('Y-m-d H:i:s'),
            'ik' => $permit->interner_kommentar,
            'agr' => \json_encode($permit->agreements, \JSON_UNESCAPED_UNICODE),
            'bez' => $permit->bezahlt_am?->format('Y-m-d H:i:s'),
            'rem' => $permit->status->last_reminder_at?->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByCode(string $code): ?Permit
    {
        $stmt = $this->pdo->prepare('SELECT * FROM permits WHERE code = :code');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new Permit(
            new PermitCode((string) $row['code']),
            new TemplateKey((string) $row['template_key']),
            new Owner(
                (string) $row['name'],
                empty($row['email']) ? null : new EmailAddress((string) $row['email']),
                new PlotNumber((int) $row['parzelle']),
            ),
            new Vehicle(
                (string) $row['typ'],
                new LicensePlate(empty($row['kennzeichen']) ? 'XXX-XX 9999' : (string) $row['kennzeichen']),
                $row['firma'] ?: null,
            ),
            new Validity(
                new DateTimeImmutable($row['von']),
                new DateTimeImmutable($row['bis']),
                new Price((float) $row['preis']),
                (string) $row['zweck'],
            ),
            new Status(
                PermitStatus::tryFrom((string) $row['status']) ?? PermitStatus::Offen,
                (bool) $row['is_suspended'],
                $row['suspension_reason'] ?: null,
                $row['last_reminder_at'] ? new DateTimeImmutable($row['last_reminder_at']) : null,
            ),
            new DateTimeImmutable($row['erstellt']),
            $row['interner_kommentar'] ?: null,
            \json_decode((string) $row['agreements'], true) ?: [],
            $row['bezahlt_am'] ? new DateTimeImmutable($row['bezahlt_am']) : null,
        );
    }

    public function delete(string $code): void
    {
        $this->pdo->prepare('DELETE FROM permits WHERE code = :code')->execute(['code' => $code]);
    }
}
