<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class GcashSetting extends Model
{
    /** @var list<string> */
    protected $fillable = ['account_name', 'account_number', 'qr_code_path'];

    public function isConfigured(): bool
    {
        return filled($this->account_name) && filled($this->account_number) && filled($this->qr_code_path);
    }
}
