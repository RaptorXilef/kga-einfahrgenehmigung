<?php

declare(strict_types=1);

namespace App\Modules\Permit\Infrastructure;

use App\Contracts\Utils\ClockInterface;
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
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use DateTimeImmutable;
use Override;
use PDO;

final readonly class PdoPermitRepository implements PermitRepositoryInterface
{
    public function __construct(
        private PDO $pdo,
        private ClockInterface $clock = new SystemClock(),
    ) {
    }

    #[Override]
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

        // Explizite Null-Prüfung für Intelephense
        $emailValue = $permit->owner->email instanceof EmailAddress ? (string) $permit->owner->email : null;

        // Nutzt nun sauber die öffentlichen Getter für private Domain-States!
        $this->pdo->prepare($sql)->execute([
            'code' => $permit->code->value,
            'tpl' => $permit->template_key->value,
            'name' => $permit->owner->name,
            'email' => $emailValue,
            'plate' => $permit->vehicle->kennzeichen->value,
            'plot' => $permit->owner->parzelle->value,
            'typ' => $permit->vehicle->typ,
            'firma' => $permit->vehicle->firma,
            'zweck' => $permit->validity->zweck,
            'preis' => $permit->validity->preis->amount,
            'von' => $permit->validity->von->format('Y-m-d'),
            'bis' => $permit->validity->bis->format('Y-m-d'),
            'status' => $permit->getStatus()->value,
            'sus' => (int) $permit->isSuspended(),
            'susr' => $permit->getSuspensionReason(),
            'erst' => $permit->erstellt->format('Y-m-d H:i:s'),
            'ik' => $permit->getInternalComment(),
            'agr' => \json_encode($permit->agreements, \JSON_UNESCAPED_UNICODE),
            'bez' => $permit->getPaidAt()?->format('Y-m-d H:i:s'),
            'rem' => $permit->getStatusObject()->last_reminder_at?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Override]
    public function findByCode(string $code): ?Permit
    {
        $stmt = $this->pdo->prepare('SELECT * FROM permits WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    #[Override]
    public function findByLicensePlate(string $plate): ?Permit
    {
        $searchPlate = \preg_replace('/[^A-Z0-9]/', '', \strtoupper($plate));

        if ($searchPlate === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT * FROM `permits` WHERE REPLACE(REPLACE(kennzeichen, ' ', ''), '-', '') = ?",
        );
        $stmt->execute([$searchPlate]);

        $candidates = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $candidates[] = $this->mapRowToEntity($row);
        }

        if ($candidates === []) {
            return null;
        }

        $now = $this->clock->now();

        // Sortierung: 1. Aktive Genehmigungen zuerst, 2. nach dem Enddatum (neueste zuerst)
        \usort($candidates, function (Permit $a, Permit $b) use ($now): int {
            $aValid = $a->isValid(false, $now);
            $bValid = $b->isValid(false, $now);

            if ($aValid && !$bValid) {
                return -1;
            }
            if (!$aValid && $bValid) {
                return 1;
            }

            return $b->validity->bis <=> $a->validity->bis;
        });

        return $candidates[0];
    }

    #[Override]
    public function yieldAllWithEmail(): iterable
    {
        $stmt = $this->pdo->query("SELECT * FROM permits WHERE email IS NOT NULL AND email != '' AND email != '0'");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $this->mapRowToEntity($row);
        }
    }

    #[Override]
    public function yieldExpired(DateTimeImmutable $cutoffDate): iterable
    {
        $stmt = $this->pdo->prepare("SELECT * FROM permits WHERE bis < :cutoff AND status IN ('bezahlt', 'storniert')");
        $stmt->execute(['cutoff' => $cutoffDate->format('Y-m-d')]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $this->mapRowToEntity($row);
        }
    }

    #[Override]
    public function yieldUnpaid(): iterable
    {
        $stmt = $this->pdo->query("SELECT * FROM permits WHERE status = 'offen' AND is_suspended = 0");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $this->mapRowToEntity($row);
        }
    }

    #[Override]
    public function delete(string $code): void
    {
        $this->pdo->prepare('DELETE FROM permits WHERE code = :code')->execute(['code' => $code]);
    }

    #[Override]
    public function deleteMultiple(array $codes): int
    {
        if ($codes === []) {
            return 0;
        }

        $placeholders = \implode(',', \array_fill(0, \count($codes), '?'));
        $stmt = $this->pdo->prepare("DELETE FROM `permits` WHERE code IN ($placeholders)");
        $stmt->execute(\array_values($codes));

        return $stmt->rowCount();
    }

    #[Override]
    public function hasCollision(int $plotNumber, DateTimeImmutable $start, DateTimeImmutable $end, string $licensePlate, ?string $company): bool
    {
        $searchPlate = \preg_replace('/[^A-Z0-9]/', '', \strtoupper($licensePlate));
        $query = 'SELECT 1 FROM permits WHERE parzelle = ? AND von <= ? AND bis >= ? AND status != \'storniert\' AND (';
        $params = [$plotNumber, $end->format('Y-m-d'), $start->format('Y-m-d')];
        $conditions = [];

        // Prüfen auf echtes Kennzeichen (Platzhalter XXX-XX 9999 ignorieren)
        if ($searchPlate !== '' && $searchPlate !== 'XXXXX9999') {
            $conditions[] = "REPLACE(REPLACE(kennzeichen, ' ', ''), '-', '') = ?";
            $params[] = $searchPlate;
        }

        // Prüfen auf Firmenname
        if ($company !== null && \trim($company) !== '') {
            $conditions[] = 'firma = ?';
            $params[] = \trim($company);
        }

        // Fallback: Wenn jemand weder Firma noch echtes Kennzeichen hat, ist es immer eine Kollision
        if ($conditions === []) {
            $query .= ' 1=1 ';
        } else {
            $query .= \implode(' OR ', $conditions);
        }

        $query .= ') LIMIT 1';

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    #[Override]
    public function isCodeUnique(string $code): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT 1 FROM permits WHERE code = ?
            UNION SELECT 1 FROM permits_archive WHERE code = ?
            UNION SELECT 1 FROM permits_cancelled WHERE code = ? LIMIT 1
        ');
        $stmt->execute([$code, $code, $code]);

        return !(bool) $stmt->fetchColumn();
    }

    /**
     * Mappt einen rohen Datenbank-Datensatz auf die Domain Entität.
     */
    private function mapRowToEntity(array $row): Permit
    {
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
}
