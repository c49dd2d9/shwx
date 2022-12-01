<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class AliPayJob implements ShouldQueue
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
        try {
            DB::beginTransaction();
            // 修改订单状态
            DB::table('ds_pay_recharge')->where('id', $data['orderid'])->update([
                'payon' => 1,
                'thirdorderno' => $data['trade_no'],
                'message_info' => now().'到账完成【AliPay】'
            ]);
            // 增加用户货币
            DB::table('ds_user_info')->where('id', $data['userid'])->increment('gold', $data['gold']);
            if ($data['yuepiao'] != 0) {
                // 如果方案赠送月票
                DB::table('ds_user_info')->where('id', $data['userid'])->increment('yuepiao', $data['yuepiao']);
            }
            if ($data['tuijian'] != 0) {
                // 如果有催更票，因为不送推荐票，只送催更票，所以拿tuijian这个字段来用。。。
                // 可恶的网文云，气死我了
                DB::table('ds_user_info')->where('id', $data['userid'])->increment('cuigengpiao', $data['tuijian']);
            }
            DB::commit();
            // 清空订单防火墙
            Redis::del('userpay:count:'.$data['userid']);
            // 重置订单列表缓存
            Cache::forget('user:order:'.$data['userid'].'_1');
        }   catch (\Throwable $th) {
             DB::rollBack();
             Log::error('[AliPay_Job]['.$data['userid'].']['.$data['trade_no'].']ErrorMessage:'.$th->getMessage());
            return 'fail';
        }
    }
}
