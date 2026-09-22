<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SendPaymentReminders;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Utils\ClockInterface;
use App\Core\Event\PaymentReminderEvent;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitFinancialCalculator;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Domain\PermitStatus;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use DateTimeImmutable;

/**
 * @implements CommandHandlerInterface<SendPaymentRemindersCommand>
 */
final readonly class SendPaymentRemindersHandler implements CommandHandlerInterface
{
    public function __construct(
        private PermitRepositoryInterface $repository,
        private ConfigInterface $config,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
        private PermitFinancialCalculator $financialCalculator,
    ) {
    }

    public function handle(mixed $command): void
    {
        if ($command->code !== null && $command->code !== '') {
            $this->dispatchReminder($command->code, $command->forceManual);

            return;
        }

        // Hochperformanter Aufruf für den Cronjob: Nur offene Permits in den RAM laden!
        $unpaidPermits = $this->repository->findUnpaid();
        foreach ($unpaidPermits as $permit) {
            $this->dispatchReminder($permit->code->value, false);
        }
    }

    private function dispatchReminder(string $code, bool $forceManual): bool
    {
        $permit = $this->repository->findByCode($code);
        if (!$permit instanceof Permit) {
            return false;
        }

        if ($permit->getStatus() !== PermitStatus::Offen || $permit->isSuspended()) {
            return false;
        }

        $now = $this->clock->now();
        $dueDateStart = $this->financialCalculator->calculatePaymentDueDate($permit)->setTime(0, 0, 0);

        if (!$forceManual && $now < $dueDateStart) {
            return false;
        }

        $cooldownDays = (int) $this->config->get('payment_reminder_cooldown_days', 7);
        if ($permit->getStatusObject()->last_reminder_at instanceof DateTimeImmutable) {
            $cooldownDate = $permit->getStatusObject()->last_reminder_at->modify("+{$cooldownDays} days");
            if ($now < $cooldownDate) {
                return false;
            }
        }

        $permit->recordReminder($now);

        // Wir nutzen hier save, da PermitRepositoryInterface::save das Update regelt
        $this->repository->save($permit);
        $this->eventDispatcher->dispatch(new PaymentReminderEvent($permit));

        return true;
    }
}
