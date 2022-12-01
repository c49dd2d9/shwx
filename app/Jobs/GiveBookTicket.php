<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class GiveBookTicket implements ShouldQueue
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
        $dbFieldName = $data['db_field_name'];
        $count = $data['count'];
        try {
            DB::beginTransaction();
            DB::table('ds_user_info')->where('id', $data['userid'])->decrement($dbFieldName, $count);
            DB::table('ds_book_info')->where('id', $data['bookid'])->increment($dbFieldName, $count);
            switch ($dbFieldName) {
                case 'yuepiao':
                    DB::table('ds_user_yplog')->insert([
                        'userid' => $data['userid'],
                        'bookid' => $data['bookid'],
                        'yuepiao' => $count,
                    ]);
                    break;
                
                default:
                    DB::table('ds_user_piao')->insert([
                        'listtype' => $data['ticket_type'],
                        'bookid' => $data['bookid'],
                        'userid' => $data['userid'],
                        'cnt' => $data['count'],
                    ]);
                    break;
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
        }
    }
}
