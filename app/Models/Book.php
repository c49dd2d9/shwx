<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $table = 'ds_book_info';
    // 不自动维护时间戳
    public $timestamps = false;
}
