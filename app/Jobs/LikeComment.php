<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class LikeComment implements ShouldQueue
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
        $userId = $data['userid'];
        $commentId = $data['commentid'];
        try {
            DB::transaction();
            DB::table('ds_book_comment')->where('id', $commentId)->increment('priasenum');
            DB::table('ds_comment_dianzan')->insert([
                'comment_id' => $commentId,
                'user_id' => $userId,
            ]);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            
        }
    }
}
