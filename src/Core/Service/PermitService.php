<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Storage\CancelledPermitRepositoryInterface;
use App\Contracts\Storage\LockManagerInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Contracts\Utils\ClockInterface;
use App\Core\DTO\PermitFormData;
use App\Core\Entity\Owner;
use App\Core\Entity\Permit;
use App\Core\Entity\PermitStatus;
use App\Core\Entity\Status;
use App\Core\Entity\Validity;
use App\Core\Entity\Vehicle;
use App\Core\Entity\VerificationRequest;
use App\Core\Entity\Voucher;
use App\Core\Event\PaymentReminderEvent;
use App\Core\Event\PermitCancelledEvent;
use App\Core\Event\PermitCreatedEvent;
use App\Core\Event\VerificationRequestedEvent;
use App\Core\Exception\PermitCollisionException;
use App\Core\Utils\DateRangeHelper;
use App\Core\ValueObject\LicensePlate;
use App\Core\ValueObject\PermitCode;
use App\Core\ValueObject\PlotNumber;
use App\Core\ValueObject\Price;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitService
{
    public function __construct(
        private CancelledPermitRepositoryInterface $cancelledRepository,
        private ClockInterface $clock,
        private ConfigInterface $config,
        private EventDispatcherInterface $eventDispatcher,
        private JsonHelperInterface $jsonHelper,
        private LockManagerInterface $lockManager,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private StorageInterface $storage,
        private VerificationRepositoryInterface $verificationRepository,
        private VoucherService $voucherService,
    ) {
    }

    public function createPendingVerification(array $data): string
    {
        // 1. PlotNumber strikt instanziieren
        $plotVO = new PlotNumber($data['parzelle'] ?? '');

        // 2. Maximalen Wert aus der Config prüfen (Fallback: 9999)
        $maxPlot = (int) $this->config->get('max_plot_number', 9999);
        if ($plotVO->toInt() > $maxPlot) {
            throw new \InvalidArgumentException("Die eingegebene Parzelle {$plotVO->toInt()} existiert nicht. Das Maximum in dieser Anlage ist {$maxPlot}.");
        }

        $this->validateNoCollisions(
            $plotVO->value,
            new \DateTimeImmutable((string) ($data['datum_von'] ?? 'now')),
            new \DateTimeImmutable((string) ($data['datum_bis'] ?? 'now')),
        );

        $tKey      = $data['template_key'] ?? 'std_7';
        $templates = (array) $this->config->get('permit_templates', []);
        $template  = $templates[$tKey] ?? $templates['std_7'];

        $vehicleTypes = (array) $this->config->get('vehicle_types', []);
        $defaultType  = $vehicleTypes === [] ? 'pkw' : \array_key_first($vehicleTypes);
        $typ          = $data['typ'] ?? $defaultType;

        $data['preis'] = (float) ($template['prices'][$typ] ?? ($template['prices'][$defaultType] ?? 0.0));

        $token     = \bin2hex(\random_bytes(32));
        $shortCode = \strtoupper(\substr(\bin2hex(\random_bytes(4)), 0, 6));

        $data['verification_token'] = $token;
        $data['verification_code']  = $shortCode;

        $hours   = (int) $this->config->get('hours_pending_verify', 24);
        $expires = $this->clock->now()->modify("+{$hours} hours");

        $req = new VerificationRequest($token, $expires, $data);

        $allPending         = $this->verificationRepository->loadPending();
        $allPending[$token] = $req;

        $this->verificationRepository->savePending($allPending);
        $this->eventDispatcher->dispatch(new VerificationRequestedEvent($data, $token, $shortCode));

        return $token;
    }

    public function updateVerifiedRequest(string $token, string $sessionEmail, array $newData): string
    {
        $oldData = $this->getVerifiedRequest($token);

        if ($oldData === null || \strtolower($newData['email']) !== \strtolower(\trim($sessionEmail))) {
            $this->createPendingVerification($newData);

            return 'redirect_verify';
        }

        $priceRelevantChanged = ($oldData['template_key'] ?? '') !== $newData['template_key']
            || ($oldData['typ'] ?? '') !== $newData['typ']
            || ($oldData['voucher'] ?? '') !== $newData['voucher'];

        if (! $priceRelevantChanged) {
            $merged           = \array_merge($oldData, $newData);
            $merged['preis']  = $oldData['preis'] ?? 0;
            $merged['status'] = PermitStatus::Offen->value;

            $allVerified         = $this->verificationRepository->loadVerified();
            $expires             = $allVerified[$token]->expiresAt ?? $this->clock->now()->modify('+48 hours');
            $allVerified[$token] = new VerificationRequest($token, $expires, $merged);

            $this->verificationRepository->saveVerified($allVerified);

            return 'redirect_checkout';
        }

        $allVerified = $this->verificationRepository->loadVerified();
        if (isset($allVerified[$token])) {
            unset($allVerified[$token]);
            $this->verificationRepository->saveVerified($allVerified);
        }

        $this->createPendingVerification($newData);

        return 'redirect_verify';
    }

    public function confirmEmail(string $input): ?array
    {
        $allPending   = $this->verificationRepository->loadPending();
        $input        = \strtoupper(\trim($input));
        $matchedToken = null;

        foreach ($allPending as $t => $req) {
            $strToken = (string) $t;
            if (\strtoupper($strToken) === $input || \strtoupper((string) ($req->data['verification_code'] ?? '')) === $input) {
                $matchedToken = $strToken;

                break;
            }
        }

        if ($matchedToken === null) {
            $allVerified = $this->verificationRepository->loadVerified();
            foreach ($allVerified as $t => $req) {
                $strToken = (string) $t;
                if (\strtoupper($strToken) === $input || \strtoupper((string) ($req->data['verification_code'] ?? '')) === $input) {
                    return $req->data;
                }
            }

            return null;
        }

        $token = $matchedToken;
        $req   = $allPending[$token];
        $data  = $req->data;

        unset($allPending[$token]);
        $this->verificationRepository->savePending($allPending);

        $hours               = (int) $this->config->get('hours_pending_finalize', 48);
        $expires             = $this->clock->now()->modify("+{$hours} hours");
        $data['verified_at'] = $this->clock->nowAsString();

        $voucherCodeStr = \strtoupper(\trim((string) ($data['voucher'] ?? '')));
        if ($voucherCodeStr !== '') {
            $voucher = $this->voucherService->useVoucher($voucherCodeStr, $data);

            if ($voucher !== null) {
                $originalPriceVO   = new Price((float) $data['preis']);
                $discountedPriceVO = $this->calculateDiscountedPrice($originalPriceVO, $voucher);
                $finalPrice        = $discountedPriceVO->value;

                if ($finalPrice <= 0.0) {
                    $data['preis']  = 0.0;
                    $data['status'] = PermitStatus::Bezahlt->value;

                    $allVerified         = $this->verificationRepository->loadVerified();
                    $allVerified[$token] = new VerificationRequest($token, $expires, $data);
                    $this->verificationRepository->saveVerified($allVerified);

                    return [
                        'finalised' => $this->finaliseRequest(
                            $token,
                            PermitStatus::Bezahlt,
                            'Gutschein (Voll-Rabatt): ' . $voucherCodeStr,
                        ),
                    ];
                }

                $data['preis']           = $finalPrice;
                $data['voucher_applied'] = $voucherCodeStr;
                $data['voucher_details'] = ['type' => $voucher->type, 'value' => $voucher->value];
            }
        }

        $allVerified         = $this->verificationRepository->loadVerified();
        $allVerified[$token] = new VerificationRequest($token, $expires, $data);
        $this->verificationRepository->saveVerified($allVerified);

        $data['actual_token'] = $token;

        return $data;
    }

    public function finaliseRequest(string $token, PermitStatus $status = PermitStatus::Offen, ?string $kommentar = null): Permit
    {
        return $this->lockManager->executeWithLock('checkout', function () use ($token, $status, $kommentar) {
            $allVerified = $this->verificationRepository->loadVerified();

            if (! isset($allVerified[$token])) {
                throw new \RuntimeException('Antragssitzung abgelaufen oder bereits abgeschlossen.');
            }

            $data                       = $allVerified[$token]->data;
            $data['status']             = $status->value;
            $data['interner_kommentar'] = $kommentar;

            $dto    = PermitFormData::fromArray($data);
            $permit = $this->createPermit($dto, true);

            unset($allVerified[$token]);
            $this->verificationRepository->saveVerified($allVerified);

            return $permit;
        });
    }

    public function createPermit(PermitFormData $data, bool $sendMails = true): Permit
    {
        // Obergrenze der Parzelle bei direkter/manueller Ausstellung prüfen
        $maxPlot = (int) $this->config->get('max_plot_number', 9999);
        if ($data->parzelle->toInt() > $maxPlot) {
            throw new \InvalidArgumentException("Die eingegebene Parzelle {$data->parzelle->toInt()} existiert nicht. Das Maximum in dieser Anlage ist {$maxPlot}.");
        }

        $tKeyStr   = $data->templateKey->value;
        $templates = (array) $this->config->get('permit_templates', []);
        $template  = (array) ($templates[$tKeyStr] ?? $templates['std_7']);

        $startDate = new \DateTimeImmutable($data->datumVon);

        if ($template['days'] === 'custom') {
            $endDate = new \DateTimeImmutable($data->datumBis);
        } else {
            $daysToAdd = \max(0, (int) $template['days'] - 1);
            $endDate   = $startDate->modify('+' . $daysToAdd . ' days');
        }

        $typ   = $data->typ;
        $preis = $data->manualPrice->value > 0.0
            ? $data->manualPrice->value
            : (float) ($template['prices'][$typ] ?? 0.0);

        $preisVO = new Price($preis);

        do {
            $randomId = $this->generateV4Suffix();

            $displayPlate    = $data->kennzeichen->value;
            $identifierPlate = \str_replace(' ', '-', $displayPlate);
            $platePart       = $identifierPlate !== '' ? $identifierPlate : \strtoupper($typ);

            $useLongCode = (bool) $this->config->get('use_long_permit_code', false);

            if ($useLongCode) {
                $fullIdentifier = \sprintf(
                    '%s-%s-%s-%s',
                    $this->config->get('prefix', 'ML'),
                    \str_pad($data->parzelle->value, 4, '0', \STR_PAD_LEFT),
                    $platePart,
                    $randomId,
                );
            } else {
                $fullIdentifier = $randomId;
            }
        } while (! $this->isCodeGloballyUnique($fullIdentifier));

        $purposes = (array) $this->config->get('purposes', []);
        $zweck    = (string) ($purposes[$data->zweck] ?? 'Privat');

        $permit = new Permit(
            code: new PermitCode($fullIdentifier),
            template_key: clone $data->templateKey,
            owner: new Owner(
                \strip_tags($data->name),
                $data->email,
                clone $data->parzelle,
            ),
            vehicle: new Vehicle(
                $typ,
                clone $data->kennzeichen,
                $data->firma ? \strip_tags($data->firma) : null,
            ),
            validity: new Validity($startDate, $endDate, $preisVO, $zweck),
            status: new Status($data->status),
            erstellt: $this->clock->now(),
            interner_kommentar: $data->internerKommentar,
            agreements: $data->agreements,
        );

        if (! $this->storage->save($permit)) {
            throw new \RuntimeException('Speicherfehler.');
        }

        if ($sendMails) {
            $this->eventDispatcher->dispatch(new PermitCreatedEvent($permit, $randomId));
        }

        return $permit;
    }

    public function manualActivate(string $code, ?string $grund = null, ?string $buchungsdatum = null): bool
    {
        $permit = $this->storage->findByHash($code);

        if (! $permit instanceof Permit) {
            return false;
        }

        // Parse das übergebene Buchungsdatum flexibel in ein echtes DateTimeImmutable-Objekt
        $dtBezahltAm = null;
        if ($buchungsdatum) {
            $dateObj = \DateTimeImmutable::createFromFormat('d.m.y', \trim($buchungsdatum));
            if ($dateObj === false) {
                $dateObj = \DateTimeImmutable::createFromFormat('d.m.Y', \trim($buchungsdatum));
            }
            $dtBezahltAm = $dateObj !== false ? $dateObj : $this->clock->now();
        } else {
            $dtBezahltAm = $this->clock->now();
        }

        $aktuellerKommentar = $permit->interner_kommentar ?? '';
        if ($grund && ! \str_contains($aktuellerKommentar, $grund)) {
            $neuerKommentar = $aktuellerKommentar !== '' ? $aktuellerKommentar . ' | ' . $grund : $grund;
        } else {
            $neuerKommentar = $aktuellerKommentar;
        }

        $updated = new Permit(
            code: $permit->code,
            template_key: $permit->template_key,
            owner: $permit->owner,
            vehicle: $permit->vehicle,
            validity: $permit->validity,
            status: new Status(PermitStatus::Bezahlt, $permit->isSuspended(), $permit->getSuspensionReason(), $permit->status->reminder_sent),
            erstellt: $permit->getCreatedAt(), // WICHTIG: Erstellungsdatum bleibt absolut unangetastet!
            interner_kommentar: $neuerKommentar,
            agreements: $permit->agreements,
            state: null,
            bezahlt_am: $dtBezahltAm, // Mapped sauber in die neue JSON/SQL Spalte
        );

        return $this->storage->save($updated);
    }

    public function toggleSuspension(string $code, bool $status, ?string $reason = null): bool
    {
        $permit = $this->storage->findByHash($code);

        if (! $permit instanceof Permit) {
            return false;
        }

        $updated = new Permit(
            $permit->code,
            $permit->template_key,
            $permit->owner,
            $permit->vehicle,
            $permit->validity,
            new Status($permit->getStatus(), $status, $reason),
            $permit->getCreatedAt(),
            $permit->interner_kommentar,
        );

        return $this->storage->save($updated);
    }

    public function searchAndPaginate(string $query, string $tab, string $templateType, int $page, int $limit): array
    {
        $allActive = $this->storage->getAll();
        $archived  = [];

        if (\in_array($tab, ['all', 'archive'], true)) {
            $arcCfg      = $this->config->get('storage_config')['permits_archive'];
            $archivePath = $this->config->getStoragePath($arcCfg['file'] ?? 'permits_archive.json');

            if (\file_exists($archivePath)) {
                $rawArchive = $this->jsonHelper->read($archivePath);
                foreach ($rawArchive as $item) {
                    $archived[] = $this->storage->mapToEntity($item);
                }
            }
        }

        $combined        = \array_merge($allActive, $archived);
        $filtered        = [];
        $queryLower      = \strtolower($query);
        $now             = $this->clock->now();
        $permitTemplates = $this->config->get('permit_templates', []);

        foreach ($combined as $permit) {
            if ($templateType !== 'all') {
                $tplType = $permitTemplates[$permit->template_key->value]['type'] ?? 'standard';
                if ($tplType !== $templateType) {
                    continue;
                }
            }

            $isArchived = $this->archiveRepository->isCodeInArchive($permit->code->value);
            $isExpired  = $permit->isExpired($now);

            if ($tab === 'active' && ($isArchived || $isExpired)) {
                continue;
            }
            if ($tab === 'expired' && (! $isExpired || $isArchived)) {
                continue;
            }
            if ($tab === 'archive' && ! $isArchived) {
                continue;
            }

            if (! $permit->matchesSearch($queryLower)) {
                continue;
            }

            $filtered[] = $permit;
        }

        \usort($filtered, fn ($a, $b): int => $b->getCreatedAt() <=> $a->getCreatedAt());

        $total  = \count($filtered);
        $offset = ($page - 1) * $limit;
        $items  = \array_slice($filtered, $offset, $limit);

        $formattedItems = \array_map(fn ($permit): array => [
            'bis'          => $permit->getValidUntil()->format('d.m.Y'),
            'code'         => $permit->code->value,
            'email'        => $permit->getOwnerEmail(),
            'erstellt'     => $permit->getCreatedAt()->format('d.m.Y H:i'),
            'is_archived'  => $this->archiveRepository->isCodeInArchive($permit->code->value),
            'kennzeichen'  => $permit->getLicensePlate(),
            'name'         => $permit->getOwnerName(),
            'parzelle'     => $permit->getPlotNumber(),
            'preis'        => $permit->getPrice(),
            'status'       => $permit->getStatus()->value,
            'template_key' => $permit->template_key->value,
            'von'          => $permit->getValidFrom()->format('d.m.Y'),
            'zweck'        => $permit->getPurpose(),
        ], $items);

        return ['items' => $formattedItems, 'total' => $total];
    }

    public function getHistoryByEmail(string $email): array
    {
        $all = $this->storage->getAll();

        return \array_filter($all, fn (Permit $permit): bool => \strtolower($permit->getOwnerEmail()) === \strtolower($email));
    }

    public function getVerifiedRequest(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $all = $this->verificationRepository->loadVerified();

        return isset($all[$token]) ? $all[$token]->data : null;
    }

    /**
     * Berechnet das dynamische Zahlungsziel einer Genehmigung.
     *
     * @param  Permit             $permit Die zu prüfende Genehmigung
     * @return \DateTimeImmutable Das berechnete Fälligkeitsdatum
     */
    public function calculatePaymentDueDate(Permit $permit): \DateTimeImmutable
    {
        $dueDays            = (int) $this->config->get('payment_due_days', 14);
        $daysBeforeValidity = (int) $this->config->get('payment_due_days_before_validity', 2);

        // Fallback: Mindestens X Tage ab Erstellung Zeit zum Bezahlen
        $fallbackDueDate = $permit->getCreatedAt()->modify("+{$dueDays} days")->setTime(23, 59, 59);

        // Dynamisch: X Tage vor Gültigkeitsbeginn
        $dynamicDueDate = $permit->getValidFrom()->modify("-{$daysBeforeValidity} days")->setTime(23, 59, 59);

        // Ist der dynamische Zeitpunkt kleiner (früher) als der Fallback, gilt der Fallback
        return $dynamicDueDate > $fallbackDueDate ? $dynamicDueDate : $fallbackDueDate;
    }

    /**
     * Ermittelt die aktuelle Mahnstufe einer Genehmigung.
     *
     * @param  Permit $permit Die zu prüfende Genehmigung
     * @return int    0 = Im Zeitrahmen/Bezahlt, 1 = Mahnfrist, 2 = Überfällig
     */
    public function getOverdueLevel(Permit $permit): int
    {
        if ($permit->getStatus() === PermitStatus::Bezahlt) {
            return 0;
        }

        $now          = $this->clock->now();
        $userDeadline = $this->calculatePaymentDueDate($permit);

        $notifyDays          = (int) $this->config->get('payment_due_days_notify', 2);
        $staffAlertThreshold = $userDeadline->modify("+{$notifyDays} days");

        if ($now > $staffAlertThreshold) {
            return 2;
        }

        if ($now > $userDeadline) {
            return 1;
        }

        return 0;
    }

    public function autoArchiveExpiredPermits(int $graceDays = 0): int
    {
        $allPermits    = $this->storage->getAll();
        $toArchive     = [];
        $codesToDelete = [];
        $cutoffDate    = $this->clock->now()->modify("-{$graceDays} days")->setTime(0, 0, 0);

        foreach ($allPermits as $permit) {
            if ($permit->getValidUntil() < $cutoffDate) {
                if (\in_array($permit->getStatus(), [PermitStatus::Bezahlt, PermitStatus::Storniert], true)) {
                    $toArchive[]     = $permit;
                    $codesToDelete[] = $permit->code->value;
                }
            }
        }

        if (! empty($toArchive)) {
            $this->archiveRepository->archivePermits(0, $toArchive);
            $this->storage->deleteMultiple($codesToDelete);
        }

        return \count($toArchive);
    }

    private function validateNoCollisions(string $parzelle, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $parzelleFormatted = \str_pad($parzelle, 4, '0', \STR_PAD_LEFT);

        foreach ($this->storage->getAll() as $permit) {
            if ($permit->hasCollision($parzelleFormatted, $start, $end)) {
                throw new PermitCollisionException(
                    "Kollision: Für Parzelle {$parzelle} existiert bereits eine Genehmigung vom " .
                    $permit->getValidFrom()->format('d.m.Y') . ' bis ' .
                    $permit->getValidUntil()->format('d.m.Y') . '.',
                );
            }
        }

        $allPending = $this->verificationRepository->loadPending();
        foreach ($allPending as $pendingReq) {
            $pendingData = $pendingReq->data;
            $pPlot       = \str_pad((string) ($pendingData['parzelle'] ?? ''), 4, '0', \STR_PAD_LEFT);
            $pStart      = new \DateTimeImmutable((string) ($pendingData['datum_von'] ?? 'now'));
            $pEnd        = new \DateTimeImmutable((string) ($pendingData['datum_bis'] ?? 'now'));

            if (
                $pPlot === $parzelleFormatted
                && DateRangeHelper::overlaps($pStart, $pEnd, $start, $end)
            ) {
                throw new PermitCollisionException(
                    "Hinweis: Für Parzelle {$parzelle} läuft bereits eine Anfrage für diesen Zeitraum. " .
                    'Bitte wählen Sie andere Daten.',
                );
            }
        }
    }

    private function generateV4Suffix(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $res   = '';
        for ($i = 0; $i < 8; ++$i) {
            $res .= $chars[\random_int(0, \strlen($chars) - 1)];
        }

        return $res;
    }

    private function validateEmail(string $email): void
    {
        if (\trim($email) === '') {
            return;
        }
        if (! \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Die eingegebene E-Mail-Adresse ist ungültig.');
        }
    }

    private function isCodeGloballyUnique(string $fullIdentifier): bool
    {
        if ($this->storage->findByHash($fullIdentifier) instanceof Permit) {
            return false;
        }
        if ($this->archiveRepository->isCodeInArchive($fullIdentifier)) {
            return false;
        }
        if ($this->cancelledRepository->isCodeCancelled($fullIdentifier)) {
            return false;
        }

        return true;
    }

    public function calculateDiscountedPrice(Price $originalPrice, Voucher $voucher): Price
    {
        $type  = $voucher->type;
        $value = $voucher->value;
        $orig  = $originalPrice->value;

        $newPrice = match ($type) {
            'fixed'   => $value,
            'free'    => 0.0,
            'percent' => $orig * (1 - ($value / 100)),
            default   => $orig,
        };

        return new Price(\max(0.0, $newPrice));
    }

    /**
     * Storniert eine Genehmigung durch den User, verschiebt sie anonymisiert ins Archiv und löscht sie.
     *
     * @throws \DomainException
     */
    public function cancelPermit(string $code, string $email): void
    {
        // 1. Config Check (Erlaubt die globale Einstellung Stornierungen?)
        if (! $this->config->get('allow_user_cancellation', true)) {
            throw new \DomainException('Stornierungen sind derzeit deaktiviert.');
        }

        $permit = $this->storage->findByHash($code);
        if (! $permit instanceof Permit) {
            throw new \DomainException('Genehmigung nicht gefunden.');
        }

        // 2. Sicherheits-Check: Gehört das Permit zur E-Mail der Session?
        if (\strtolower($permit->getOwnerEmail()) !== \strtolower(\trim($email))) {
            throw new \DomainException('Keine Berechtigung für diese Genehmigung.');
        }

        // 3. Domain-Regeln: Nur unbezahlte und zukünftige Permits dürfen storniert werden
        if ($permit->isPaid()) {
            throw new \DomainException('Bereits bezahlte Genehmigungen können nicht automatisch storniert werden.');
        }

        $now = $this->clock->now();
        if (! $permit->isFuture($now)) {
            throw new \DomainException('Nur Genehmigungen, deren Gültigkeit in der Zukunft liegt, können storniert werden.');
        }

        // 4. E-Mail Event triggern (MUSS VOR der Anonymisierung passieren, damit die E-Mail noch bekannt ist)
        $this->eventDispatcher->dispatch(new PermitCancelledEvent($permit));

        // 5. DSGVO-konforme Anonymisierung durchführen und Status auf 'storniert' setzen
        $anonymizedPermit = new Permit(
            code: $permit->code,
            template_key: $permit->template_key,
            owner: new Owner('[ANONYMISIERT]', null, new PlotNumber('0000')),
            vehicle: new Vehicle($permit->getVehicleType(), new LicensePlate('XXX-XX 9999'), '[ANONYMISIERT]'),
            validity: clone $permit->validity,
            status: new Status(PermitStatus::Storniert, false, 'Durch Pächter storniert', false),
            erstellt: $permit->getCreatedAt(),
            interner_kommentar: $permit->interner_kommentar,
            agreements: $permit->agreements,
            state: null,
            bezahlt_am: null,
        );

        // 6. Ins Archiv verschieben
        $this->cancelledRepository->saveCancelled($anonymizedPermit);

        // 7. Aus produktiver Datenbank löschen
        $this->storage->delete($permit->code->value);
    }

    /**
     * Sucht nach unbezahlten Genehmigungen, bei denen der letzte Zahlungstag erreicht wurde,
     * setzt den reminder_sent Flag und versendet die E-Mails.
     *
     * @return int Anzahl der verschickten Erinnerungen
     */
    public function sendPaymentReminders(): int
    {
        $now        = $this->clock->now();
        $sentCount  = 0;
        $allPermits = $this->storage->getAll();

        foreach ($allPermits as $permit) {
            // Nur offene Permits betrachten, die weder gesperrt sind, noch bereits eine Erinnerung bekamen
            if ($permit->getStatus() !== PermitStatus::Offen || $permit->isSuspended() || $permit->status->reminder_sent) {
                continue;
            }

            // Wir holen den berechneten letzten Zahlungstag und setzen ihn auf 00:00:00 Uhr
            $dueDateStart = $this->calculatePaymentDueDate($permit)->setTime(0, 0, 0);

            // Wenn "heute" der letzte Zahlungstag (oder ein Tag danach) ist: Erinnerung senden
            if ($now >= $dueDateStart) {

                // 1. Status aktualisieren, BEVOR die Mail gesendet wird (Schützt vor Dauerschleifen bei Mail-Fehlern)
                $updatedStatus = new Status(
                    $permit->status->current,
                    $permit->status->is_suspended,
                    $permit->status->suspension_reason,
                    true, // reminder_sent auf true
                );

                $updatedPermit = new Permit(
                    $permit->code,
                    $permit->template_key,
                    $permit->owner,
                    $permit->vehicle,
                    $permit->validity,
                    $updatedStatus,
                    $permit->getCreatedAt(),
                    $permit->interner_kommentar,
                    $permit->agreements,
                    $permit->state,
                    $permit->bezahlt_am,
                );

                $this->storage->save($updatedPermit);

                // 2. Event zum E-Mail-Versand auslösen
                $this->eventDispatcher->dispatch(new PaymentReminderEvent($updatedPermit));

                ++$sentCount;
            }
        }

        return $sentCount;
    }
}
