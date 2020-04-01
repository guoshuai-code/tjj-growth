<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-04-10
 * Time: 18:01
 */

namespace app\activity\model;

use think\facade\Cache;


class Activityredis
{

    protected $redis;
    const STRING_GOOD_TIME = 'string_good_time:';
    const STRING_GARDEN_INFO = 'string_garden_info:';
    const STRING_SUMMER_INFO = 'string_summer_info:';
    const STRING_LOVE_INFO = 'string_love_info:';
    const STRING_CHILD_INFO = 'string_child_info:';
    const HASH_ACTIVITY_COUPON_LIST='hash_activity_coupon_list:';

    const STRING_MIDDLE_ACTIVITY_GOODS='string_middle_activity_goods:';

    const LIST_USER_MASCOT='list_user_mascot:';

    const STRING_MASCOT_EXISTS_DATE='string_mascot_exists_date:';

    //淘好礼
    const STRING_TAO_GOODS_INFO='string_tao_goods_info:';
    //免费送的淘任务
    const STRING_FREE_TAO_RENWU='string_free_tao_renwu:';
    //赚赚618 用户领取任务key
    const STRING_ZZ_ACTIVITY_MIDDLE='string_zz_activity_middle:';

    /*
     * 赚赚百团黑名单 key
     */
    const ZZBT_BLACKLIST_USER = 'zzbtdz_';

    public function __construct()
    {
        $this->redis = Cache::store('redis')->handler();
    }

    //写入 家纺好时光
    public function setgoodtime($info)
    {
        $this->redis->SETEX(self::STRING_GOOD_TIME . date('Y-m-d'), 300, json_encode($info));
    }

    //读取 家纺好时光
    public function getgoodtime()
    {
        return $this->redis->get(self::STRING_GOOD_TIME . date('Y-m-d'));
    }

    //写入 浓浓果园
    public function setgardeninfo($info)
    {
        $this->redis->setex(self::STRING_GARDEN_INFO . date('Y-m-d'), 300, json_encode($info));
    }

    //读取 浓浓果园
    public function getgardeninfo()
    {
        return $this->redis->get(self::STRING_GARDEN_INFO . date('Y-m-d'));
    }

    //写入 炎炎夏日
    public function setsummerinfo($info)
    {
        $this->redis->setex(self::STRING_SUMMER_INFO . date('Y-m-d'), 300, json_encode($info));
    }

    //读取 炎炎夏日
    public function getsummerinfo()
    {
        return $this->redis->get(self::STRING_SUMMER_INFO . date('Y-m-d'));
    }

    //写入 520活动
    public function setloveinfo($info,$type)
    {
        $this->redis->setex(self::STRING_LOVE_INFO.date('Y-m-d').':'.$type,300,json_encode($info));
    }

    //读取 520活动
    public function getloveinfo($type)
    {
        return $this->redis->get(self::STRING_LOVE_INFO.date('Y-m-d').':'.$type);
    }

    //写入 526活动
    public function setchildinfo($info)
    {
        $this->redis->setex(self::STRING_CHILD_INFO.date('Y-m-d'),300,json_encode($info));
    }

    //读取 526活动
    public function getchildinfo()
    {
        return $this->redis->get(self::STRING_CHILD_INFO.date('Y-m-d'));
    }

    //写入 618活动 万券齐发页面
    public function hsetcouponinfo($key,$val)
    {
        $this->redis->hset(self::HASH_ACTIVITY_COUPON_LIST,$key,json_encode($val));
        $this->redis->expire(self::HASH_ACTIVITY_COUPON_LIST,86400);
    }

    public function hgetcouponinfo($key)
    {
        return $this->redis->hget(self::HASH_ACTIVITY_COUPON_LIST,$key);
    }

    public function hexistscouponinfo($key)
    {
        return $this->redis->HEXISTS(self::HASH_ACTIVITY_COUPON_LIST,$key);
    }

    //写入618活动商品缓存
    public function setgoodsinfo($val,$tabId,$page)
    {
        $this->redis->setex(self::STRING_MIDDLE_ACTIVITY_GOODS.date('Y-m-d').':tab_id:'.$tabId.':page'.$page,600,json_encode($val));
    }

    //读取618活动商品信息缓存
    public function getgoodsinfo($tabId,$page)
    {
        return $this->redis->get(self::STRING_MIDDLE_ACTIVITY_GOODS.date('Y-m-d').':tab_id:'.$tabId.':page'.$page);
    }

    //写入订单信息生成吉祥物信息
    public function lpushMascot($userId,$mascotName)
    {
          $this->redis->lpush(self::LIST_USER_MASCOT.$userId,$mascotName.','.time());
    }


    //读取订单生成吉祥物信息
    public function lrangeMascot($userId)
    {
        return $this->redis->lrange(self::LIST_USER_MASCOT.$userId,0,-1);
    }

    //删除已经弹过吉祥物信息
    public function delKeys($userId)
    {
        $this->redis->del(self::LIST_USER_MASCOT.$userId);
    }

    //判断优惠券是否存在
    public function incrmascot($date)
    {
        return $this->redis->incr(self::STRING_MASCOT_EXISTS_DATE.$date);
    }

    //判断优惠券领取限额
    public function getmascotDay($date)
    {
        $info=$this->redis->exists(self::STRING_MASCOT_EXISTS_DATE.$date);
        if(empty($info)){
            $this->redis->setnx(self::STRING_MASCOT_EXISTS_DATE.$date,0);
        }
        return $this->redis->get(self::STRING_MASCOT_EXISTS_DATE.$date);

    }

    //618 淘好礼 写入
    public function settaogoodsinfo($info)
    {
        $this->redis->SETEX(self::STRING_TAO_GOODS_INFO . date('Y-m-d'), 600, json_encode($info));
    }

    //618 淘好礼 读取
    public function gettaogoodsinfo()
    {
        return $this->redis->get(self::STRING_TAO_GOODS_INFO . date('Y-m-d'));
    }

    //判断618活动 是否给用户赠送过淘任务
    public function gefreettaoinfo($userId)
    {
      return $this->redis->get(self::STRING_FREE_TAO_RENWU.$userId);

    }

    //给用户免费淘人物
    public function setfreetaoinfo($userId)
    {

        $this->redis->setnx(self::STRING_FREE_TAO_RENWU.$userId,time());
    }

    //赚赚618活动 获取用户领取信息
    public function getzzsix($userId)
    {
      return $this->redis->get(self::STRING_ZZ_ACTIVITY_MIDDLE.$userId);
    }

    //赚赚618活动 设置用户领取信息
    public function setzzsix($userId)
    {
       $this->redis->setnx(self::STRING_ZZ_ACTIVITY_MIDDLE.$userId,time());
    }
}