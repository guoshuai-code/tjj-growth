<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-03-13
 * Time: 20:06
 */

namespace app\zhuanzhuan\model;

use think\facade\Cache;


class MRedis
{

    private $redis;
    const HASH_USER_INFO = 'hash_user_info:';
    //判断用户打开首页弹框KEY前缀
    const STRING_USER_BT_EXISTS = 'string_user_bt_exists:';
    //用户签到 key前缀
    const STRING_USER_SIGN_IN = 'string_user_sign_in:';
    //用户签到次数
    const STRING_USER_SIGN_IN_COUNT = 'string_user_sign_in_count:';
    //用户浏览 key前缀
    const STRING_USER_VIEW_GOOD = 'string_user_view_good:';
    //用户浏览次数
    const STRING_USER_VIEW_GOOD_COUNT = 'string_user_view_good_count:';
    //百团大战 今日奖金发放
    const STRING_BTDD_TODAY_AWARD = 'string_btdd_today_award:';

    public function __construct()
    {
        $this->redis = Cache::store('redis')->handler();

    }

    //用户信息写入缓存
    public function hsetUserCache($uid, $val)
    {
        $this->redis->hset(self::HASH_USER_INFO, $uid, $val);
    }

    public function hgetUserCache($uid)
    {
        return $this->redis->hget(self::HASH_USER_INFO, $uid);
    }

    //判断key 是否存在
    public function exitsKey($str)
    {
        return $this->redis->EXISTS($str);
    }

    //活动首页弹框  缓存用户信息
    public function setBtUser($user_id)
    {
        $this->redis->setnx(self::STRING_USER_BT_EXISTS . $user_id, time());
        $this->redis->EXPIREAT(self::STRING_USER_BT_EXISTS . $user_id, strtotime(date('Y-m-d 23:59:59')));
    }

    //签到 缓存用户信息
    public function setBtSignIn($user_id)
    {
        $this->redis->multi();
        $this->redis->setnx(self::STRING_USER_SIGN_IN.date('Y-m-d').':' . $user_id, time());
        $this->redis->set(self::STRING_USER_SIGN_IN_COUNT.date('Y-m-d').':' . $user_id, 1);
        $this->redis->EXPIREAT(self::STRING_USER_SIGN_IN.date('Y-m-d') .':'. $user_id, strtotime(date('Y-m-d 23:59:59')));
        $this->redis->EXPIREAT(self::STRING_USER_SIGN_IN_COUNT.date('Y-m-d').':' . $user_id, strtotime(date('Y-m-d 23:59:59')));
        $this->redis->exec();
    }

    //签到 获取用户信息
    public function getBtSignIn($user_id)
    {
        $cacheTime = $this->redis->get(self::STRING_USER_SIGN_IN.date('Y-m-d').':' . $user_id);
        $cacheCount = $this->redis->get(self::STRING_USER_SIGN_IN_COUNT.date('Y-m-d').':' . $user_id);
        return ['cache_time' => $cacheTime, 'cache_count' => $cacheCount];
    }

    //签到 用户签到次数自增
    public function userSignCount($user_id)
    {
        $this->redis->incr(self::STRING_USER_SIGN_IN_COUNT.date('Y-m-d').':' . $user_id);
    }

    //浏览商品 缓存用户信息
    public function setBtViewGood($user_id)
    {
        $this->redis->multi();
        $this->redis->setnx(self::STRING_USER_VIEW_GOOD.date('Y-m-d').':' . $user_id, time());
        $this->redis->set(self::STRING_USER_VIEW_GOOD_COUNT.date('Y-m-d').':' . $user_id, 1);
        $this->redis->EXPIREAT(self::STRING_USER_VIEW_GOOD.date('Y-m-d').':' . $user_id, strtotime(date('Y-m-d 23:59:59')));
        $this->redis->EXPIREAT(self::STRING_USER_VIEW_GOOD_COUNT.date('Y-m-d').':' . $user_id, strtotime(date('Y-m-d 23:59:59')));
        $this->redis->exec();
    }

    //浏览商品 获取用户信息
    public function getBtViewGood($user_id)
    {
        $cacheTime = $this->redis->get(self::STRING_USER_VIEW_GOOD.date('Y-m-d').':' . $user_id);
        $cacheCount = $this->redis->get(self::STRING_USER_VIEW_GOOD_COUNT.date('Y-m-d').':' . $user_id);
        return ['cache_time' => $cacheTime, 'cache_count' => $cacheCount];
    }

    //浏览商品 用户签到次数自增
    public function userViewCount($user_id)
    {
        $this->redis->incr(self::STRING_USER_VIEW_GOOD_COUNT.date('Y-m-d').':' . $user_id);
    }


    //百团大战 设置今日奖金金额  奖金缓存7天
    public function setBtAward($money)
    {
        $info = $this->redis->exists(self::STRING_BTDD_TODAY_AWARD . date('Y-m-d'));
        if (empty($info)) {
            $this->redis->setnx(self::STRING_BTDD_TODAY_AWARD . date('Y-m-d'), 0);
            $this->redis->EXPIRE(self::STRING_BTDD_TODAY_AWARD . date('Y-m-d'), 86400*7);
        } else {
           $this->redis->incrby(self::STRING_BTDD_TODAY_AWARD . date('Y-m-d'), $money);
        }
    }

    //百团大战 获取奖金
    public function getBtAward($time){
        return $this->redis->get(self::STRING_BTDD_TODAY_AWARD . $time);
    }
}