<?php

namespace App\Modules\Logs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logs\Models\SystemLog;
use App\Modules\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SystemLogController extends Controller
{
    private const ERROR_STATUSES = ['failed', 'rejected', 'blocked', 'invalid'];

    private const WARNING_STATUSES = ['started', 'pending', 'pending_verification', 'processing'];

    private const SUCCESS_NOTIFICATION_STATUSES = ['sent', 'success', 'succeeded'];

    /** @var array<string, string> */
    private const CATEGORIES = [
        'payments'       => 'Payments',
        'orders'         => 'Pautang / Orders',
        'points'         => 'Points',
        'notifications'  => 'Notifications',
        'security'       => 'Security',
        'authentication' => 'Authentication',
        'scheduler'      => 'Scheduler',
        'system'         => 'System',
    ];

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $today   = now('Asia/Manila')->toDateString();
        $filters = $request->validate([
            'search'    => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'type'      => ['nullable', 'in:all,'.implode(',', SystemLog::TYPES)],
            'module'    => ['nullable', 'string', 'max:50'],
            'action'    => ['nullable', 'string', 'max:100'],
            'user_id'   => ['nullable', 'integer'],
            'status'    => ['nullable', 'string', 'max:50'],
            'severity'  => ['nullable', 'in:all,error,warning'],
            'category'  => ['nullable', 'in:all,'.implode(',', array_keys(self::CATEGORIES))],
        ]);

        $resolvedFilters = [
            'search'    => $filters['search']    ?? '',
            'date_from' => $filters['date_from'] ?? $today,
            'date_to'   => $filters['date_to']   ?? $today,
            'type'      => $filters['type']      ?? 'all',
            'module'    => $filters['module']    ?? 'all',
            'action'    => $filters['action']    ?? 'all',
            'user_id'   => $filters['user_id']   ?? null,
            'status'    => $filters['status']    ?? 'all',
            'severity'  => $filters['severity']  ?? 'all',
            'category'  => $filters['category']  ?? 'all',
        ];

        $query = $this->filteredQuery($resolvedFilters);
        $logs  = (clone $query)
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SystemLog $log) => $this->logData($log));

        return Inertia::render('modules/logs/SystemIndex', [
            'logs'      => $logs,
            'filters'   => $resolvedFilters,
            'types'     => SystemLog::TYPES,
            'modules'   => SystemLog::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module')->values(),
            'statuses'  => SystemLog::query()->whereNotNull('status')->distinct()->orderBy('status')->pluck('status')->values(),
            'users'     => User::query()->orderBy('name')->get(['id', 'name']),
            'dashboard' => [
                'summary'    => $this->summary($query),
                'attention'  => $this->attention($query),
                'health'     => $this->health($query),
                'categories' => $this->categoryActivity($query),
            ],
        ]);
    }

    public function show(Request $request, SystemLog $systemLog): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $systemLog->load('user:id,name');

        return response()->json(['log' => $this->detailData($systemLog)]);
    }

    /** @param array<string, mixed> $filters */
    private function filteredQuery(array $filters): Builder
    {
        return SystemLog::query()
            ->with('user:id,name')
            ->when($filters['search'] !== '', fn (Builder $query) => $this->search($query, $filters['search']))
            ->when($filters['date_from'] !== '', fn (Builder $query) => $query->where('created_at', '>=', $this->manilaDay($filters['date_from'])->startOfDay()->utc()))
            ->when($filters['date_to'] !== '', fn (Builder $query) => $query->where('created_at', '<=', $this->manilaDay($filters['date_to'])->endOfDay()->utc()))
            ->when($filters['type'] !== 'all', fn (Builder $query) => $query->where('type', $filters['type']))
            ->when($filters['module'] !== 'all', fn (Builder $query) => $query->where('module', $filters['module']))
            ->when($filters['action'] !== 'all', fn (Builder $query) => $query->where('action', $filters['action']))
            ->when($filters['user_id'] !== null, fn (Builder $query) => $query->where('user_id', $filters['user_id']))
            ->when($filters['status'] !== 'all', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['severity'] !== 'all', fn (Builder $query) => $this->applySeverity($query, $filters['severity']))
            ->when($filters['category'] !== 'all', fn (Builder $query) => $this->applyCategory($query, $filters['category']));
    }

    private function manilaDay(string $date): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $date, 'Asia/Manila');
    }

    private function applySeverity(Builder $query, string $severity): Builder
    {
        return match ($severity) {
            'error'   => $query->whereIn('status', self::ERROR_STATUSES),
            'warning' => $query->whereIn('status', self::WARNING_STATUSES),
            default   => $query,
        };
    }

    private function applyCategory(Builder $query, string $category): Builder
    {
        return match ($category) {
            'payments'       => $query->where('module', 'payments'),
            'orders'         => $query->whereIn('module', ['orders', 'pautang']),
            'points'         => $query->where('module', 'points'),
            'notifications'  => $query->where('module', 'notifications'),
            'security'       => $query->where('type', 'security'),
            'authentication' => $query->where('module', 'authentication'),
            'scheduler'      => $query->where('module', 'notifications')->where('action', 'like', 'reminder_%'),
            'system'         => $query->where('type', 'system'),
            default          => $query,
        };
    }

    private function search(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $searchQuery) use ($search): void {
            $searchQuery
                ->where('description', 'like', "%{$search}%")
                ->orWhere('action', 'like', "%{$search}%")
                ->orWhere('module', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%")
                ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('name', 'like', "%{$search}%"));

            if (ctype_digit($search)) {
                $searchQuery
                    ->orWhere('id', (int) $search)
                    ->orWhere('record_id', (int) $search);
            }
        });
    }

    /** @return array<string, int> */
    private function summary(Builder $query): array
    {
        return [
            'total_events'             => (clone $query)->count(),
            'errors'                   => (clone $query)->whereIn('status', self::ERROR_STATUSES)->count(),
            'warnings'                 => (clone $query)->whereIn('status', self::WARNING_STATUSES)->count(),
            'security_events'          => (clone $query)->where('type', 'security')->count(),
            'failed_notifications'     => (clone $query)->where('module', 'notifications')->whereIn('status', self::ERROR_STATUSES)->count(),
            'successful_notifications' => (clone $query)->where('module', 'notifications')->whereIn('status', self::SUCCESS_NOTIFICATION_STATUSES)->count(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function attention(Builder $query): Collection
    {
        return (clone $query)
            ->whereIn('status', self::ERROR_STATUSES)
            ->selectRaw('action, module, count(*) as count, max(created_at) as latest_at')
            ->groupBy('action', 'module')
            ->orderByDesc('latest_at')
            ->limit(5)
            ->get()
            ->map(fn (SystemLog $log) => [
                'action'    => $log->action,
                'module'    => $log->module,
                'count'     => (int) $log->count,
                'latest_at' => Carbon::parse($log->latest_at)->toISOString(),
            ])
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function health(Builder $query): Collection
    {
        return collect([
            $this->healthSignal(
                query: $this->matchingQuery($query, fn (Builder $healthQuery) => $healthQuery->where('module', 'notifications')->where('action', 'like', 'push_%')),
                key: 'web_push',
                label: 'Web Push',
                successfulStatuses: self::SUCCESS_NOTIFICATION_STATUSES,
            ),
            $this->healthSignal(
                query: $this->matchingQuery($query, fn (Builder $healthQuery) => $healthQuery->where('module', 'notifications')->where('action', 'like', 'reminder_%')),
                key: 'scheduler',
                label: 'Scheduler',
                successfulStatuses: ['completed'],
            ),
            $this->securityHealth($query),
        ]);
    }

    private function matchingQuery(Builder $query, callable $callback): Builder
    {
        $matchingQuery = clone $query;
        $callback($matchingQuery);

        return $matchingQuery;
    }

    /**
     * @param  list<string>  $successfulStatuses
     * @return array<string, mixed>
     */
    private function healthSignal(Builder $query, string $key, string $label, array $successfulStatuses): array
    {
        $failures    = (clone $query)->whereIn('status', self::ERROR_STATUSES)->count();
        $lastSuccess = (clone $query)
            ->whereIn('status', $successfulStatuses)
            ->latest()
            ->first(['created_at']);

        if ($failures > 0) {
            return ['key' => $key, 'label' => $label, 'state' => 'attention', 'count' => $failures, 'latest_at' => null];
        }

        if ($lastSuccess) {
            return ['key' => $key, 'label' => $label, 'state' => 'recent_success', 'count' => 0, 'latest_at' => $lastSuccess->created_at->toISOString()];
        }

        return ['key' => $key, 'label' => $label, 'state' => 'no_data', 'count' => 0, 'latest_at' => null];
    }

    /** @return array<string, mixed> */
    private function securityHealth(Builder $query): array
    {
        $securityQuery        = $this->matchingQuery($query, fn (Builder $healthQuery) => $healthQuery->where('type', 'security'));
        $failures             = (clone $securityQuery)->whereIn('status', self::ERROR_STATUSES)->count();
        $lastSuccessfulAction = (clone $securityQuery)
            ->whereIn('action', ['login_succeeded', 'password_reset_completed', 'password_changed', 'account_enabled'])
            ->latest()
            ->first(['created_at']);

        if ($failures > 0) {
            return ['key' => 'security', 'label' => 'Authentication & Security', 'state' => 'attention', 'count' => $failures, 'latest_at' => null];
        }

        if ($lastSuccessfulAction) {
            return ['key' => 'security', 'label' => 'Authentication & Security', 'state' => 'recent_success', 'count' => 0, 'latest_at' => $lastSuccessfulAction->created_at->toISOString()];
        }

        return ['key' => 'security', 'label' => 'Authentication & Security', 'state' => 'no_data', 'count' => 0, 'latest_at' => null];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function categoryActivity(Builder $query): Collection
    {
        return collect(self::CATEGORIES)
            ->map(function (string $label, string $key) use ($query): array {
                $categoryQuery = clone $query;
                $this->applyCategory($categoryQuery, $key);

                return [
                    'key'   => $key,
                    'label' => $label,
                    'logs'  => $categoryQuery->latest()->limit(3)->get()->map(fn (SystemLog $log) => $this->logData($log))->values(),
                ];
            })
            ->filter(fn (array $category) => count($category['logs']) > 0)
            ->values();
    }

    /** @return array<string, mixed> */
    private function logData(SystemLog $log): array
    {
        return [
            'id'          => $log->id,
            'type'        => $log->type,
            'action'      => $log->action,
            'module'      => $log->module,
            'description' => $log->description,
            'status'      => $log->status,
            'user'        => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
            'created_at'  => $log->created_at->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function detailData(SystemLog $log): array
    {
        return [
            ...$this->logData($log),
            'record_id'  => $log->record_id,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'metadata'   => $this->redactMetadata($log->metadata),
        ];
    }

    private function redactMetadata(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $key => $item) {
            $key            = (string) $key;
            $redacted[$key] = $this->isSensitiveMetadataKey($key)
                ? '[Redacted]'
                : $this->redactMetadata($item);
        }

        return $redacted;
    }

    private function isSensitiveMetadataKey(string $key): bool
    {
        return (bool) preg_match('/password|token|secret|private[._-]?key|vapid|authorization|credential/i', $key);
    }
}
