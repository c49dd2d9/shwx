<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class UserGiftBook implements ShouldQueue
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
        /**
         * 送礼操作
         * 1、查询山海币是否足以支付（应在评论Api中完成）
         * 2、如果足以支付，则扣除当前用户的山海币
         * 3、根据签约分成，写入作者稿酬和作品稿酬中
         * 4、写入作者收礼记录
         * 5、写入读者消费记录
         * 6、写入ds_gift_data
         * 7、发布评论
         */
        try {
            DB::beginTransaction();
            // 扣除礼物等额的山海币
            DB::table('ds_user_info')->where('id', $data['userInfo']['id'])->decrement('gold', $data['giftInfo']['price']); 
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return errorJson();
        }
    }
}
