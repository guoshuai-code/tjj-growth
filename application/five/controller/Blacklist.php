<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-03-22
 * Time: 15:51
 */
namespace app\five\controller;

use think\App;
use think\Controller;
use think\facade\Log;
use think\Request;
use app\zhuanzhuan\model\MRedis;

class Blacklist extends Controller
{
    protected $redis;

    const ZZBLACKLIST='zzblacklist_';

    public function __construct(App $app = null)
    {
        parent::__construct($app);
        $this->redis=new MRedis();
    }

    public function getBlackInfo(Request $request)
    {
//        $uid=$request->param('user_id');
        $uid=$_GET['user_id'];
        if(empty($uid))
            return returnJosn([],'100001');
        Log::info('判断用户ID为<'.$uid.'>是否为黑名单');
        $info=$this->redis->exitsKey(self::ZZBLACKLIST.$uid);
        if($info==1){
            return returnJosn([],'100003');
        }else{
            return returnJosn([],'1');
        }
    }
}