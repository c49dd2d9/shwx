<?php

namespace App\Enums;

use BenSampo\Enum\Enum;

/**
 * @method static static OptionOne()
 * @method static static OptionTwo()
 * @method static static OptionThree()
 */
final class UserConfig extends Enum
{
    const VerifyCodeEfficientsecond = 300; // 验证码有效时间，单位：秒
    const AllowResendTimeLeftsecond = 280; // 在x秒的剩余时间内，允许用户再次发送验证码（即过去VerifyCodeEfficientsecond - AllowResendTimeLeftsecond 秒后允许用户再次发送）
    const UserTokenEfficient = 24 * 3600;// Token有效时间，单位：秒
    const UserAccountLocked = 1; // 锁定标识
    const AdminGroupId = 1;// Admin用户组Id
    const UserLockedRedisCacheEfficientsecond = 300; // 用户被锁定后加的缓存锁的有效时间，单位：秒
    const NoticeRedisSecond = 7 * 24 * 3600;// 公告缓存时间
    const MaxLevel = 10;// 最大等级
    const ContinuousSignInThreshold = 7; // 签到额外附赠礼品的时间阈值，即大于等于X天后会额外赠送小礼物(?)
    const GoldName = '山海币'; // 销售货币名称
    const UserTokenRefrshTime = 60 * 24 * 3600; // 重置用户 Token 时间
    const UserTokenDelimiter = '_'; // Token + Salt的分隔符
    const UserPayLimitCount = 20; // 限制 5min 内订单生成数量
    const AliPayOrderEfficient = '30m';  // 交易关闭时间（支付宝）
    const UserChangeNicknameSecond = 180 * 24 * 3600; // 下一次修改昵称的时间，单位：秒
    const UserChangeHeadimgSecond = 10; // 下一次修改头像的时间，单位：秒
}
