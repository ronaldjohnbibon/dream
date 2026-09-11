<?php

namespace App\Modules\Logs\Services;

use App\Modules\Logs\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SystemLogger
{
    public function __construct(private readonly Request $request) {}

    /** @param array<string, mixed>|null $metadata */
    public function record(
        string $type,
        string $action,
        string $description,
        ?string $module = null,
        ?int $recordId = null,
        ?string $status = null,
        ?array $metadata = null,
        ?int $actorId = null,
    ): ?SystemLog {
        try {
            return SystemLog::create([
                'user_id'     => $actorId ?? $this->request->user()?->id,
                'type'        => $type,
                'action'      => $action,
                'module'      => $module,
                'record_id'   => $recordId,
                'description' => $description,
                'status'      => $status,
                'ip_address'  => $this->request->ip(),
                'user_agent'  => $this->request->userAgent(),
                'metadata'    => $metadata,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Unable to create system log.', [
                'type'            => $type,
                'action'          => $action,
                'module'          => $module,
                'record_id'       => $recordId,
                'exception_class' => $exception::class,
            ]);

            return null;
        }
    }
}
