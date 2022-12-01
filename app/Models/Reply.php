<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reply extends Model
{
    static public function suffix($topicId)
    {
        $topicSuffix = substr($topicId, -1);
        if ($topicSuffix == 0) {
            $topicSuffix = 1;
        }
        return 'ds_board_reply_'.$topicSuffix;
    }
}
