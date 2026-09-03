<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'business_name', 'logo_path', 'contact_number', 'address',
        'pautang_enabled', 'pautang_installments', 'pautang_payment_term_days',
        'pautang_max_active', 'pautang_max_sacks', 'pautang_grace_period_days',
        'free_delivery_area_ids', 'low_stock_threshold',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'pautang_enabled' => 'boolean',
            'pautang_installments' => 'integer',
            'pautang_payment_term_days' => 'integer',
            'pautang_max_active' => 'integer',
            'pautang_max_sacks' => 'integer',
            'pautang_grace_period_days' => 'integer',
            'free_delivery_area_ids' => 'array',
            'low_stock_threshold' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], [
            'business_name' => 'Business Starter',
            'pautang_enabled' => true,
            'pautang_installments' => 2,
            'pautang_payment_term_days' => 30,
            'pautang_max_active' => 1,
            'pautang_max_sacks' => 1,
            'pautang_grace_period_days' => 3,
            'free_delivery_area_ids' => [],
            'low_stock_threshold' => 5,
        ]);
    }

    public function isFreeDeliveryArea(int $areaId): bool
    {
        return in_array($areaId, $this->free_delivery_area_ids ?? [], true);
    }
}
