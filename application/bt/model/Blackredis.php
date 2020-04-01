<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-04-10
 * Time: 18:01
 */

namespace app\bt\model;

use think\facade\Cache;


class Blackredis
{

    protected $config;
    protected $redis;

    /*
     * 赚赚百团黑名单 key
     */
    const ZZBT_BLACKLIST_USER='zzbtdz_';

    public function __construct()
    {
        $this->config = \think\facade\Config::get('cache.black_list_redis');
        $this->redis = Cache::connect($this->config)->handler();
    }

    public function getBtBlackList($userId)
    {
        return $this->redis->exists(self::ZZBT_BLACKLIST_USER.$userId);
    }
}