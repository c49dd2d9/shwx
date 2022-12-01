<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'ds_user_info';
    // 不自动维护时间戳
    public $timestamps = false;
}
