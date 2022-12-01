<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class UpdateBookShelfUpdateInfo implements ShouldQueue
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
         * 传递2个参数
         * 1、bookid
         * 2、更新时间
         * 3、作品名
         */
        $getListId = DB::table('ds_user_collect_book')->where('bookid', $data['bookid'])->pluck('bookshelfid');
        DB::table('ds_user_collect_bookshelf')->whereIn('id', $getListId)->update([
            'lastupdatebookname' => $data['bookname'],
            'lastupdatetime' => $data['updatetime'], 
        ]);
    }
}
