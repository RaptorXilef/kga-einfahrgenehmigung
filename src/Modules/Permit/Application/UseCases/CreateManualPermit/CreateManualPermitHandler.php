<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\CreateManualPermit;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\Events\PermitCreatedEvent;
use App\Modules\Permit\Domain\Owner;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Domain\Status;
use App\Modules\Permit\Domain\Validity;
use App\Modules\Permit\Domain\Vehicle;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use App\SharedKernel\Domain\ValueObject\PermitCode;
use DateTimeImmutable;

/**
 * @implements CommandHandlerInterface<CreateManualPermitCommand>
 */
final readonly class CreateManualPermitHandler implements CommandHandlerInterface
{
    public function __construct(
        private PermitRepositoryInterface $repository,
        private ConfigInterface $config,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param CreateManualPermitCommand $command
     */
    public function handle(mixed $command): void
    {
        $data = $command->formData;

        $templates = (array) $this->config->get('permit_templates', []);
        $template = (array) ($templates[$data->templateKey->value] ?? $templates['std_7'] ?? ['days' => 1]);

        $startDate = new DateTimeImmutable($data->datumVon);
        if (($template['days'] ?? 1) === 'custom') {
            $endDate = new DateTimeImmutable($data->datumBis);
        } else {
            $daysToAdd = \max(0, (int) $template['days'] - 1);
            $endDate = $startDate->modify('+' . $daysToAdd . ' days');
        }

        $purposes = (array) $this->config->get('purposes', []);
        $zweckRaw = $data->zweck;
        $zweck = $purposes[$zweckRaw] ?? ($zweckRaw !== '' ? \strip_tags($zweckRaw) : 'Privat');

        // Generiere eindeutige Code ID
        do {
            $randomId = $this->generateV4Suffix();
            $platePart = \str_replace(' ', '-', $data->kennzeichen->value);

            $useLongCode = (bool) $this->config->get('use_long_permit_code', false);
            if ($useLongCode) {
                $fullIdentifier = \sprintf(
                    '%s-%s-%s-%s',
                    $this->config->get('prefix', 'ML'),
                    $data->parzelle->getFormatted(),
                    $platePart,
                    $randomId,
                );
            } else {
                $fullIdentifier = $randomId;
            }
        } while ($this->repository->findByCode($fullIdentifier) !== null);

        $permit = new Permit(
            code: new PermitCode($fullIdentifier),
            template_key: clone $data->templateKey,
            owner: new Owner(\strip_tags($data->name), $data->email, clone $data->parzelle),
            vehicle: new Vehicle($data->typ, clone $data->kennzeichen, $data->firma ? \strip_tags($data->firma) : null),
            validity: new Validity($startDate, $endDate, $data->manualPrice, $zweck),
            status: new Status($data->status),
            erstellt: $this->clock->now(),
            interner_kommentar: $data->internerKommentar,
            agreements: $data->agreements,
        );

        $this->repository->save($permit);

        if ($command->sendEmail) {
            $this->eventDispatcher->dispatch(new PermitCreatedEvent($permit, $randomId));
        }
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
