<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Enums\BookConfig;

class ArchivePublishChapter implements ShouldQueue
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
        $data = $this->$data;
        $chapterInfo = DB::table('ds_book_chapter')->where('id', $data['chapterid'])->first();
        if ($chapterInfo->publishtime != $data['publishtime']) {
            DB::table('ds_chapter_job')->where('id', $data['jobid'])->update([
                'state' => 1,
            ]);
        }
        if ($chapterInfo->is_temp == 0) {
            DB::table('ds_chapter_job')->where('id', $data['jobid'])->update([
                'state' => 1,
            ]);
        }
        $book = DB::table('ds_book_info')->where('id', $data['bookid'])->first();
        try {
            DB::beginTransaction();
            // 修改章节状态
            DB::table('ds_book_chapter')->where('id', $data['chapterid'])->update([
                'status' => 1,
                'istimepublish' => 0,
                'haspublished' => 1,
                'istemp' => 0,
            ]);
            // 将书籍推送到当日更新表中
            DB::table('ds_daliy_update_book')->insert([
                'bookid' => $data['bookid'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // 修改作品字数/修改最后章节id/修改作品最后发布时间
            DB::table('ds_book_info')->where('id', $data['bookid'])->increment('bookcnt', $data['chaptercnt'], ['lastchapterid' => $data['chapterid'], 'lastchapterupdatetime' => $data['publishtime']]);
            // 修改作品章节总数
            DB::table('ds_book_info')->where('id', $data['bookid'])->increment('chaptercnt', 1);
            // 更新此章节
            DB::table('ds_chapter_job')->where('id', $data['jobid'])->update([
                'state' => 1,
            ]);
            DB::commit();
            Cache::forget('qudao_book:'.$data['bookid']);
            Cache::forget(BookConfig::ReaderChapterCacheKey.$data['bookid']);
            UpdateBookShelfUpdateInfo::dispatch([
                'bookname' => $book->bookname,
                'updatetime' => time(),
                'bookid' => $book->id,
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
        }
    }
}
