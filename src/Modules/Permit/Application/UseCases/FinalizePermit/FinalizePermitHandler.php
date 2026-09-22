<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\FinalizePermit;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Storage\LockManagerInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\Events\PermitCreatedEvent;
use App\Modules\Permit\Domain\Owner;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Domain\Status;
use App\Modules\Permit\Domain\Validity;
use App\Modules\Permit\Domain\Vehicle;
use App\Modules\Permit\Domain\VerificationRepositoryInterface;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PermitCode;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use App\SharedKernel\Domain\ValueObject\Price;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use DateTimeImmutable;
use RuntimeException;

final readonly class FinalizePermitHandler
{
    public function __construct(
        private LockManagerInterface $lockManager,
        private VerificationRepositoryInterface $verificationRepository,
        private PermitRepositoryInterface $permitRepository,
        private ConfigInterface $config,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function handle(FinalizePermitCommand $command): Permit
    {
        return $this->lockManager->executeWithLock('checkout', function () use ($command): Permit {
            $allVerified = $this->verificationRepository->loadVerified();

            if (!isset($allVerified[$command->token])) {
                throw new RuntimeException('Antragssitzung abgelaufen oder bereits abgeschlossen.');
            }

            $data = $allVerified[$command->token]->data;

            // Domain-Umwandlung (Direkt, da es verifizierte Daten sind)
            $startDate = new DateTimeImmutable($data['datum_von']);
            $templates = (array) $this->config->get('permit_templates', []);
            $template = $templates[$data['template_key']] ?? ['days' => 1];

            if (($template['days'] ?? 1) === 'custom') {
                $endDate = new DateTimeImmutable($data['datum_bis']);
            } else {
                $daysToAdd = \max(0, (int) ($template['days'] ?? 1) - 1);
                $endDate = $startDate->modify('+' . $daysToAdd . ' days');
            }

            $purposes = (array) $this->config->get('purposes', []);
            $zweckRaw = $data['zweck'] ?? '';
            $zweck = $purposes[$zweckRaw] ?? ($zweckRaw !== '' ? \strip_tags($zweckRaw) : 'Privat');

            // Unique ID Generation
            do {
                $randomId = $this->generateV4Suffix();
                $platePart = \str_replace(' ', '-', (string) ($data['kennzeichen'] ?? ''));
                if ($platePart === '') {
                    $platePart = \strtoupper($data['typ'] ?? 'PKW');
                }

                $useLongCode = (bool) $this->config->get('use_long_permit_code', false);
                $fullIdentifier = $useLongCode
                    ? \sprintf('%s-%s-%s-%s', $this->config->get('prefix', 'ML'), \str_pad((string) $data['parzelle'], 4, '0', \STR_PAD_LEFT), $platePart, $randomId)
                    : $randomId;
            } while (!$this->permitRepository->isCodeUnique($fullIdentifier));

            $emailStr = \trim((string) ($data['email'] ?? ''));

            $permit = new Permit(
                code: new PermitCode($fullIdentifier),
                template_key: new TemplateKey($data['template_key']),
                owner: new Owner(\strip_tags($data['name']), $emailStr !== '' ? new EmailAddress($emailStr) : null, new PlotNumber($data['parzelle'])),
                vehicle: new Vehicle($data['typ'] ?? 'pkw', new LicensePlate($data['kennzeichen']), $data['firma'] ? \strip_tags($data['firma']) : null),
                validity: new Validity($startDate, $endDate, new Price((float) ($data['preis'] ?? 0.0)), $zweck),
                status: new Status($command->status),
                erstellt: $this->clock->now(),
                interner_kommentar: $command->comment,
                agreements: $data['agreements'] ?? [],
            );

            $this->permitRepository->save($permit);

            // Housekeeping: Remove from pending
            unset($allVerified[$command->token]);
            $this->verificationRepository->saveVerified($allVerified);

            // Mails feuern!
            $this->eventDispatcher->dispatch(new PermitCreatedEvent($permit, $randomId));

            return $permit;
        });
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
