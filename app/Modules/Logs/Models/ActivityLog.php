<?php

namespace App\Modules\Logs\Models;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public const MODULES = ['orders', 'payments', 'points', 'customers', 'inventory', 'settings'];

    public const ACTIONS = ['created', 'cancelled', 'approved', 'rejected', 'adjusted', 'suspended', 'reactivated', 'changed'];

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'module',
        'action',
        'related_type',
        'related_id',
        'description',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
