<?php

namespace App\Modules\Notifications\Console;

use App\Modules\Notifications\Services\CustomerNotificationService;
use App\Modules\Orders\Models\PautangInstallment;
use App\Modules\Settings\Models\SystemSetting;
use Illuminate\Console\Command;

class SendInstallmentReminders extends Command
{
    protected $signature = 'notifications:send-installment-reminders';

    protected $description = 'Send in-app notifications for upcoming and overdue pautang installments.';

    public function handle(CustomerNotificationService $notifications): int
    {
        $today = now('Asia/Manila')->startOfDay();
        $upcoming = 0;
        $overdue = 0;

        $this->installmentsDueOn($today->copy()->addDays(3)->toDateString())->each(function (PautangInstallment $installment) use ($notifications, &$upcoming): void {
            if ($notifications->upcomingInstallment($installment)) {
                $upcoming++;
            }

            $notifications->administratorsUpcomingInstallment($installment);
        });

        $overdueDueDate = $today->copy()->subDays(SystemSetting::current()->pautang_grace_period_days + 1)->toDateString();
        $this->installmentsDueOn($overdueDueDate)->each(function (PautangInstallment $installment) use ($notifications, &$overdue): void {
            if ($notifications->overdueInstallment($installment)) {
                $overdue++;
            }

            $notifications->administratorsOverdueInstallment($installment);
        });

        $this->info("Sent {$upcoming} upcoming and {$overdue} overdue installment notifications.");

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, PautangInstallment> */
    private function installmentsDueOn(string $date)
    {
        return PautangInstallment::query()
            ->with(['order.customer'])
            ->where('remaining_balance', '>', 0)
            ->whereDate('due_date', $date)
            ->whereHas('order', fn ($query) => $query->where('order_status', '!=', 'cancelled'))
            ->get();
    }
}
