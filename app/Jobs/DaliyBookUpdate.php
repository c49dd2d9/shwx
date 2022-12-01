<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Bus\Dispatchable;

class DaliyBookUpdate implements ShouldQueue
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
        $yester = explode('-', date('Y-m-d', strtotime("-1 days")));
        $yesterdayMonth = $yester[1];
        $yesterday = $yester[2];
        $getUpdateChapter = DB::table('ds_book_chapter')->where('bookid', $data['bookid'])->whereDate('publishtime', date('Y-m-d', strtotime("-1 days")))->sum('wordcnt');
        DB::table('ds_book_update_info')->insert([
            'bookid' => $data['bookid'],
            'year' => $yester[0],
            'month' => $yesterdayMonth,
            'daily' => $yesterday,
            'wordcnt' => $getUpdateChapter,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
