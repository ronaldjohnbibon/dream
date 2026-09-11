<?php

namespace App\Modules\Logs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logs\Models\SystemLog;
use App\Modules\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $filters = $request->validate([
            'search'    => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'type'      => ['nullable', 'in:all,'.implode(',', SystemLog::TYPES)],
            'module'    => ['nullable', 'string', 'max:50'],
            'action'    => ['nullable', 'string', 'max:100'],
            'user_id'   => ['nullable', 'integer'],
            'status'    => ['nullable', 'string', 'max:50'],
        ]);

        $search   = $filters['search']    ?? '';
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo   = $filters['date_to']   ?? null;
        $type     = $filters['type']      ?? 'all';
        $module   = $filters['module']    ?? 'all';
        $action   = $filters['action']    ?? 'all';
        $userId   = $filters['user_id']   ?? null;
        $status   = $filters['status']    ?? 'all';

        $logs = SystemLog::query()
            ->with('user:id,name')
            ->when($search !== '', fn (Builder $query) => $this->search($query, $search))
            ->when($dateFrom !== null, fn (Builder $query) => $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay()))
            ->when($dateTo !== null, fn (Builder $query) => $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay()))
            ->when($type !== 'all', fn (Builder $query) => $query->where('type', $type))
            ->when($module !== 'all', fn (Builder $query) => $query->where('module', $module))
            ->when($action !== 'all', fn (Builder $query) => $query->where('action', $action))
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SystemLog $log) => $this->logData($log));

        return Inertia::render('modules/logs/SystemIndex', [
            'logs'    => $logs,
            'filters' => [
                'search'    => $search,
                'date_from' => $dateFrom ?? '',
                'date_to'   => $dateTo   ?? '',
                'type'      => $type,
                'module'    => $module,
                'action'    => $action,
                'user_id'   => $userId,
                'status'    => $status,
            ],
            'types'    => SystemLog::TYPES,
            'modules'  => SystemLog::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module')->values(),
            'actions'  => SystemLog::query()->distinct()->orderBy('action')->pluck('action')->values(),
            'statuses' => SystemLog::query()->whereNotNull('status')->distinct()->orderBy('status')->pluck('status')->values(),
            'users'    => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, SystemLog $systemLog): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $systemLog->load('user:id,name');

        return response()->json(['log' => $this->detailData($systemLog)]);
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
            'metadata'   => $log->metadata,
        ];
    }
}
