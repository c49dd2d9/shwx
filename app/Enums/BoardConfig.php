<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static OptionOne()
 * @method static static OptionTwo()
 * @method static static OptionThree()
 */
final class BoardConfig extends Enum
{
    const BoardRedisKey = 'board:list'; // 论坛列表redisKey
    const BoardCachesecond = 7 * 24 * 3600; // 论坛列表缓存时间
    const BoardTableName = 'ds_board_info'; // 论坛版块数据表名
    const TopicPageItem = 20; // 主题列表页每页Item数
    const ReplyPageItem = 50; // 主题内容页每页回复的Item数量
    const ReplyFireWallRedisSuffix = 'user:bbs:firewall:'; // 防水墙缓存前缀
    const ReplyRedisTTL = 60; // 缓存时间
    const ReplyFireWallCount = 5; // 防水墙数量（一分钟内发表多少回复触发拦截）
}
