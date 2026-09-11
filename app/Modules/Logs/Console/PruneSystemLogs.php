<?php

namespace App\Modules\Logs\Console;

use App\Modules\Logs\Models\SystemLog;
use Illuminate\Console\Command;

class PruneSystemLogs extends Command
{
    protected $signature = 'logs:prune-system';

    protected $description = 'Delete system logs older than their configured retention periods';

    public function handle(): int
    {
        $ordinaryDays = (int) config('system_logs.retention_days');
        $activityDays = (int) config('system_logs.activity_retention_days');
        $securityDays = (int) config('system_logs.security_retention_days');

        if ($ordinaryDays < 1 || $activityDays < 1 || $securityDays < 1) {
            $this->error('System log retention periods must be positive whole numbers.');

            return self::FAILURE;
        }

        if ($activityDays < $ordinaryDays || $securityDays < $ordinaryDays) {
            $this->error('Activity and security log retention cannot be shorter than ordinary log retention.');

            return self::FAILURE;
        }

        $now = now();

        $ordinary = SystemLog::query()
            ->whereNotIn('type', ['activity', 'security'])
            ->where('created_at', '<', $now->copy()->subDays($ordinaryDays))
            ->delete();
        $activity = SystemLog::query()
            ->where('type', 'activity')
            ->where('created_at', '<', $now->copy()->subDays($activityDays))
            ->delete();
        $security = SystemLog::query()
            ->where('type', 'security')
            ->where('created_at', '<', $now->copy()->subDays($securityDays))
            ->delete();

        $this->info("Pruned {$ordinary} ordinary, {$activity} activity, and {$security} security system logs.");

        return self::SUCCESS;
    }
}
