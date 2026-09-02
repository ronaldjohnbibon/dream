<?php

namespace App\Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class GcashSetting extends Model
{
    /** @var list<string> */
    protected $fillable = ['qr_code_path'];
}
