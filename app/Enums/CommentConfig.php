<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static OptionOne()
 * @method static static OptionTwo()
 * @method static static OptionThree()
 */
final class CommentConfig extends Enum
{
    const BookCommentPaginate = 30; // 每页获取x条书籍评论
    const ChapterCommentPaginate = 30;// 每页获取x条章节评论
    const LongCommentSize = 500;// 长评需要x字
}
