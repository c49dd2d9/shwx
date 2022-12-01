<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use App\Http\Services\GaodeMap;
use App\Enums\UserLoginConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class UserLoginProcess implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $data;
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = $this->data;
        $userIpGet = GaodeMap::getUserIpInfo($data['userip']);
        if ($userIpGet && !empty($userIpGet['city'])) {
            $loginProvinceAndCity = $userIpGet['province'].'-'.$userIpGet['city'];
        } else {
            $loginProvinceAndCity = $data['userip'] != '127.0.0.1' ? '海外/国外' : '局域网'; 
        }
        $accountType = $data['accountType'];
        if ($data['isSuccess'] == true) {
            $loginState = 'Y';
        } else {
            $loginState = 'N';
        }
        $platform = UserLoginConfig::getValue($data['platform']);
        if (!isset($data['changeInfo'])) {
            $info = '登录IP:'.$loginProvinceAndCity.' 登录方式:'.$accountType.' 登录渠道:'.$platform.' 登录成功:'.$loginState;
        } else {
            $info = $data['changeInfo'].' 变更操作IP:'.$loginProvinceAndCity;
        }
        DB::table('ds_user_login_record')->insert([
            'userid' => $data['userid'],
            'login_info' => $info,
            'created_at' => date('Y-m-d H:i:s', time()),
            'updated_at' => date('Y-m-d H:i:s', time()),
        ]);
    }
}
