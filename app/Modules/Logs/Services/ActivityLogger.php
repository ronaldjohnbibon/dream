<?php

namespace App\Modules\Logs\Services;

use App\Modules\Logs\Models\ActivityLog;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public function record(User $user, string $module, string $action, Model $related, string $description): ActivityLog
    {
        return ActivityLog::create([
            'user_id'      => $user->id,
            'module'       => $module,
            'action'       => $action,
            'related_type' => $related->getMorphClass(),
            'related_id'   => $related->getKey(),
            'description'  => $description,
        ]);
    }
}
