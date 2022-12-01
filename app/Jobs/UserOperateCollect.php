<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use App\Models\Books;
use Illuminate\Foundation\Bus\Dispatchable;

class UserOperateCollect implements ShouldQueue
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
         * 收藏操作
         * 1、往收藏表中插入一条收藏数据
         * 2、为作品增加一个收藏数据
         */
        try {
            DB::beginTransaction();
            if ($data['iscollect'] == true) {
                DB::table('ds_user_collect_book')->insert([
                    'userid' => $data['userid'],
                    'bookid' => $data['bookid'],
                    'bookshelfid' => $data['bookshelfid'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);  
                DB::table('ds_book_info')->where('id', $data['bookid'])->increment('collect');
            } else {
                DB::table('ds_user_collect_book')->where('id', $data['id'])->delete();
                DB::table('ds_book_info')->where('id', $data['bookid'])->decrement('collect');
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return 'fail';
        }
    }
}
