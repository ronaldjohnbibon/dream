<?php

namespace App\Modules\Logs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Logs\Models\ActivityLog;
use App\Modules\Orders\Models\GcashPayment;
use App\Modules\Orders\Models\Order;
use App\Modules\Points\Models\PointsLedger;
use App\Modules\Settings\Models\SystemSetting;
use App\Modules\Users\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'in:all,'.implode(',', ActivityLog::MODULES)],
            'action' => ['nullable', 'in:all,'.implode(',', ActivityLog::ACTIONS)],
        ]);
        $search = $filters['search'] ?? '';
        $module = $filters['module'] ?? 'all';
        $action = $filters['action'] ?? 'all';

        $logs = ActivityLog::query()
            ->with('user:id,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($searchQuery) => $searchQuery
                ->where('description', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"))))
            ->when($module !== 'all', fn ($query) => $query->where('module', $module))
            ->when($action !== 'all', fn ($query) => $query->where('action', $action))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => $this->logData($log));

        return Inertia::render('modules/logs/Index', [
            'logs' => $logs,
            'filters' => compact('search', 'module', 'action'),
            'modules' => ActivityLog::MODULES,
            'actions' => ActivityLog::ACTIONS,
        ]);
    }

    /** @return array<string, mixed> */
    private function logData(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'user_name' => $log->user?->name ?? 'Deleted user',
            'module' => $log->module,
            'action' => $log->action,
            'related_record' => $this->relatedRecord($log),
            'description' => $log->description,
            'created_at' => $log->created_at->toISOString(),
        ];
    }

    private function relatedRecord(ActivityLog $log): string
    {
        return match ($log->related_type) {
            Order::class => "Order #{$log->related_id}",
            GcashPayment::class => "GCash payment #{$log->related_id}",
            PointsLedger::class => "Points ledger #{$log->related_id}",
            User::class => "Customer #{$log->related_id}",
            StockMovement::class => "Stock movement #{$log->related_id}",
            SystemSetting::class => 'System settings',
            default => "Record #{$log->related_id}",
        };
    }
}
