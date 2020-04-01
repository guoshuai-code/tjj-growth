<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-03-13
 * Time: 16:56
 */

namespace app\zhuanzhuan\controller;

use think\App;
use think\Db;
use think\Request;
use app\zhuanzhuan\model\MRedis;

class User extends Baseapi
{
    private $redis;

    public function __construct(App $app = null)
    {
        parent::__construct($app);
        $this->redis = new MRedis();
    }


}