<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointsSetting extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'completed_order_points',
        'on_time_payment_points',
        'peso_per_point',
        'is_enabled',
        'minimum_redemption',
        'maximum_points_usable',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'completed_order_points' => 'integer',
            'on_time_payment_points' => 'integer',
            'peso_per_point' => 'decimal:2',
            'is_enabled' => 'boolean',
            'minimum_redemption' => 'integer',
            'maximum_points_usable' => 'integer',
        ];
    }
}
