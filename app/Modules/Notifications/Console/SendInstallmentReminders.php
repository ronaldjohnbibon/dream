<?php

namespace App\Modules\Notifications\Console;

use App\Modules\Logs\Services\SystemLogger;
use App\Modules\Notifications\Services\CustomerNotificationService;
use App\Modules\Orders\Models\PautangInstallment;
use App\Modules\Settings\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SendInstallmentReminders extends Command
{
    protected $signature = 'notifications:send-installment-reminders';

    protected $description = 'Send Web Push and in-app reminders for unpaid pautang installments.';

    public function handle(CustomerNotificationService $notifications, SystemLogger $systemLogs): int
    {
        $today        = now('Asia/Manila')->startOfDay();
        $dueTomorrow  = 0;
        $dueToday     = 0;
        $overdue      = 0;
        $reminderType = null;
        $installment  = null;

        $dueTomorrowInstallments = $this->installmentsDueOn($today->copy()->addDay()->toDateString());
        $dueTodayInstallments    = $this->installmentsDueOn($today->toDateString());
        $overdueCutoff           = $today->copy()->subDays(SystemSetting::current()->pautang_grace_period_days)->toDateString();
        $overdueInstallments     = $this->overdueInstallments($overdueCutoff);
        $candidates              = $dueTomorrowInstallments->count() + $dueTodayInstallments->count() + $overdueInstallments->count();

        if ($candidates === 0) {
            $this->info('No installment reminders to send.');

            return self::SUCCESS;
        }

        $systemLogs->record(
            type: 'system',
            action: 'reminder_scheduler_started',
            description: 'Installment reminder batch started.',
            module: 'notifications',
            status: 'started',
            metadata: [
                'run_date'                => $today->toDateString(),
                'due_tomorrow_candidates' => $dueTomorrowInstallments->count(),
                'due_today_candidates'    => $dueTodayInstallments->count(),
                'overdue_candidates'      => $overdueInstallments->count(),
            ],
        );

        try {
            $dueTomorrowInstallments->each(function (PautangInstallment $currentInstallment) use ($notifications, &$dueTomorrow, &$reminderType, &$installment): void {
                $reminderType = 'due_tomorrow';
                $installment  = $currentInstallment;
                if ($notifications->installmentDueTomorrow($currentInstallment)) {
                    $dueTomorrow++;
                }

                $notifications->administratorsUpcomingInstallment($currentInstallment);
            });

            $dueTodayInstallments->each(function (PautangInstallment $currentInstallment) use ($notifications, &$dueToday, &$reminderType, &$installment): void {
                $reminderType = 'due_today';
                $installment  = $currentInstallment;
                if ($notifications->installmentDueToday($currentInstallment)) {
                    $dueToday++;
                }
            });

            $overdueInstallments->each(function (PautangInstallment $currentInstallment) use ($notifications, &$overdue, &$reminderType, &$installment): void {
                $reminderType = 'overdue';
                $installment  = $currentInstallment;
                if ($notifications->overdueInstallment($currentInstallment)) {
                    $overdue++;
                }

                $notifications->administratorsOverdueInstallment($currentInstallment);
            });
        } catch (\Throwable $exception) {
            $systemLogs->record(
                type: 'system',
                action: 'reminder_sending_failed',
                description: 'Installment reminder sending failed.',
                module: 'notifications',
                recordId: $installment?->id,
                status: 'failed',
                metadata: [
                    'reminder_type'   => $reminderType,
                    'installment_id'  => $installment?->id,
                    'order_id'        => $installment?->order_id,
                    'customer_id'     => $installment?->order?->customer_id,
                    'error_message'   => 'Installment reminder sending failed.',
                    'exception_class' => $exception::class,
                ],
            );

            throw $exception;
        }

        $systemLogs->record(
            type: 'system',
            action: 'reminder_batch_completed',
            description: 'Installment reminder batch completed.',
            module: 'notifications',
            status: 'completed',
            metadata: [
                'run_date'                => $today->toDateString(),
                'due_tomorrow_candidates' => $dueTomorrowInstallments->count(),
                'due_today_candidates'    => $dueTodayInstallments->count(),
                'overdue_candidates'      => $overdueInstallments->count(),
                'due_tomorrow_sent'       => $dueTomorrow,
                'due_today_sent'          => $dueToday,
                'overdue_sent'            => $overdue,
            ],
        );

        $this->info("Sent {$dueTomorrow} due-tomorrow, {$dueToday} due-today, and {$overdue} overdue installment reminders.");

        return self::SUCCESS;
    }

    /** @return Collection<int, PautangInstallment> */
    private function installmentsDueOn(string $date)
    {
        return PautangInstallment::query()
            ->with(['order.customer'])
            ->where('remaining_balance', '>', 0)
            ->whereDate('due_date', $date)
            ->whereHas('order', fn ($query) => $query->where('order_status', '!=', 'cancelled'))
            ->get();
    }

    /** @return Collection<int, PautangInstallment> */
    private function overdueInstallments(string $cutoff)
    {
        return PautangInstallment::query()
            ->with(['order.customer'])
            ->where('remaining_balance', '>', 0)
            ->whereDate('due_date', '<', $cutoff)
            ->whereHas('order', fn ($query) => $query->where('order_status', '!=', 'cancelled'))
            ->get();
    }
}
