<?php

namespace App\Modules\Logs\Models;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemLog extends Model
{
    public const TYPES = ['activity', 'system', 'security'];

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'type',
        'action',
        'module',
        'record_id',
        'description',
        'status',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'record_id' => 'integer',
            'metadata'  => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
