<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-04-02
 * Time: 17:41
 */

namespace app\bt\controller;

use think\App;
use think\Controller;
use app\zhuanzhuan\model\MRedis;
use think\Db;
use think\facade\Log;
use think\Request;
use app\bt\model\BtPlayLog;
use app\bt\model\BtPlayNum;
use app\bt\model\BtShare;
use app\bt\model\BtUserGroup;
use app\bt\model\BtGroupDetail;
use app\bt\model\BtTitleInfo;
use app\bt\model\Blackredis;
use app\bt\model\BtUserPay;

class Index extends Controller
{

    //正式数钱 给第一名默认值
    private $maxNum = 5000;

    private $redis;


    //用户签到
    const USER_SIGN_IN_TYPE = 1;
    //用户浏览
    const USER_VIEW_TYPE = 2;
    //用户分享
    const USER_SHART_TYPE = 3;
    //用户注册
    const USER_REG_TYPE = 4;

    //判断用户打开首页弹框KEY前缀
    const STRING_USER_BT_EXISTS = 'string_user_bt_exists:';
    //用户试玩游戏类型
    const PLAY_GAME_TRY_TYPE = 1;
    //用户正式玩游戏类型
    const PLAY_GAME_FORMAL_TYPE = 2;
    //用户签到 key前缀
    const STRING_USER_SIGN_IN = 'string_user_sign_in:';
    //用户浏览 key前缀
    const STRING_USER_VIEW_GOOD = 'string_user_view_good:';
    private $cycleStart='';//当前周期的开始时间 年月日
    private $cycleEnd=''; //当前周期的截止时间 年月日
    private $validTime='';//当前周期内还剩几天

    //团有效期映射
    protected $validMap=[
      0=>3,
      1=>2,
      2=>1,
    ];

    public function __construct(App $app = null)
    {
        parent::__construct($app);
        $this->redis = new MRedis();
        $configTime = config('btinfo.cycle_start_time');
        $unixCTime = strtotime($configTime);
        $nowTime = strtotime(date('Y-m-d 00:00:00'));
        $diffTime = (($nowTime - $unixCTime) / 86400) % 3;

        if (array_key_exists($diffTime, $this->validMap)) {
            $this->validTime = $this->validMap[$diffTime];
        } else {
            $this->validTime = 1;
        }
        $newEndTime = $this->validTime - 1;
        //当前周期的截止时间
        $this->cycleEnd = date('Y-m-d', strtotime("+ $newEndTime days "));
        $newStartTime=strtotime($this->cycleEnd);
        //当前周期的开始时间
        $this->cycleStart=date('Y-m-d',strtotime("-2 days",$newStartTime));
    }

    //判断赚赚首页弹框 用户今日是否线显示过
    public function index(Request $request)
    {

        $userId = $request->param('user_id');
        if (empty($userId)) return returnJosn([], '100001');
        $btExists = $this->redis->exitsKey(self::STRING_USER_BT_EXISTS . $userId);
        if (empty($btExists)) {
            $this->redis->setBtUser($userId);
            return returnJosn([], '1');
        }
        return returnJosn([], '100004');

    }

    //赚赚首页弹框 开始PK按钮 判断用户是否正式玩过游戏
    public function playgame(Request $request)
    {
        try {
            $userId = $request->param('user_id');
            if (empty($userId)) return returnJosn([], '100001');
            $playLog = new BtPlayLog();
            $userPlayLog = $playLog->where('user_id', $userId)
                ->where('play_type', self::PLAY_GAME_FORMAL_TYPE)
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->value('id');
            $userTry=$playLog->where('user_id',$userId)
                ->where('play_type',self::PLAY_GAME_TRY_TYPE)
                ->value('id');
            //签到
            $mPlayNum = new BtPlayNum();
            $signinCache = $this->redis->exitsKey(self::STRING_USER_SIGN_IN.date('Y-m-d').':' . $userId);

            $userInfoId = $mPlayNum->where('user_id', $userId)
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->value('id');

            if (empty($signinCache)) {
                $this->redis->setBtSignIn($userId);
                if (empty($userInfoId)) {
                    $this->addplaynum($userId);
                } else {
                    $this->saveplaynum($userId);
                }
            }

            $res = $mPlayNum->where('user_id', $userId)
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
            ->sum('play_num');
//            var_dump($mPlayNum->getLastSql());exit;
            if (empty($userPlayLog)) {
                return returnJosn(['user_type'=>$userTry,'user_num'=>$res,'user_play_log'=>'no'], '1');
            }
            return returnJosn(['user_type'=>$userTry,'user_num'=>$res,'user_play_log'=>'yes'], '1');
        } catch (\Exception $exception) {
            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' 系统发生异常,错误信息为：' . $exception->getMessage());
            return returnJosn([], '200000');
        }
    }

    //添加玩游戏次数记录
    protected function addplaynum($userId)
    {
        $playNum = new BtPlayNum();
        $arr = [
            'user_id' => $userId,
            'create_time' => date('Y-m-d'),
            'add_time' => time(),
            'play_num' => 1,
        ];
        $res = $playNum->insert($arr);
        return $res;
    }

    //当天玩游戏次数自增1
    public function saveplaynum($user_id)
    {
        $playNum = new BtPlayNum();
        $res = $playNum->where('user_id', $user_id)
            ->where('create_time','>=',$this->cycleStart)
            ->where('create_time','<=',$this->cycleEnd)
            ->value('id');
        if(!empty($res)){
            $info=$playNum->where('id', $res)->setInc('play_num');
        }else{
            $info=$playNum->insert([
               'user_id'=>$user_id,
               'create_time'=>date('Y-m-d'),
               'play_num'=>1,
                'add_time'=>time(),
            ]);
        }
        return $info;
    }

    //当天玩游戏次数减少1
    public function decplaynum($user_id)
    {
        $playNum = new BtPlayNum();
        $res = $playNum->where('user_id', $user_id)
            ->where('create_time','>=',$this->cycleStart)
            ->where('create_time','<=',$this->cycleEnd)
            ->value('id');
        if(!empty($res)){
            $info=$playNum->where('id', $res)->setDec('play_num');
        }else{
            $info=$playNum->insert([
                'user_id'=>$user_id,
                'create_time'=>date('Y-m-d'),
                'play_num'=>1,
                'add_time'=>time(),
            ]);
        }
        return $info;
    }


    //当天是否有游戏次数
    //周期内的游戏次数
    protected function userplaynum($user_id)
    {
        $playNum = new BtPlayNum();
        $res = $playNum->where('user_id', $user_id)
            ->where('create_time','>=',$this->cycleStart)
            ->where('create_time','<=',$this->cycleEnd)
            ->sum('play_num');
        return $res;
    }

    //签到 增加用户玩游戏次数
    public function signin(Request $request)
    {
        try {
            $userId = $request->param('user_id');
            if (empty($userId)) return returnJosn([], '100001');
            $playNum = new BtPlayNum();
            $now = time();
            $signinCache = $this->redis->exitsKey(self::STRING_USER_SIGN_IN.date('Y-m-d').':' . $userId);

            $userInfoId = $playNum->where('user_id', $userId)
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->value('id');

            if (empty($signinCache)) {
                $this->redis->setBtSignIn($userId);
                if (empty($userInfoId)) {
                    $this->addplaynum($userId);
                } else {
                    $this->saveplaynum($userId);
                }
            } else {
                $userCacheInfo = $this->redis->getBtSignIn($userId);
                $cacheHour =$userCacheInfo['cache_time'];
                $cacheCount = $userCacheInfo['cache_count'];
                $diffTime = ceil(($now - $cacheHour)/3600);
                if ($diffTime > 2 && $cacheCount < 2) {
                    $this->saveplaynum($userId);
                    $this->redis->userSignCount($userId);
                }
            }
            $signTime = $this->redis->getBtSignIn($userId);
            return returnJosn(['cache_time' => $signTime['cache_time']], '1');
        } catch (\Exception $exception) {
            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' 系统发生异常,错误信息为：' . $exception->getMessage());
            return returnJosn([], '200000');
        }
    }

    //用户浏览次数
    public function userviewcount(Request $request)
    {
        try {
            $userId = $request->param('user_id');
            if (empty($userId)) return returnJosn([], '100001');
            $cacheTimes = $this->redis->getBtViewGood($userId);
            return returnJosn(['cache_time' => empty($cacheTimes['cache_time'])?0:$cacheTimes['cache_time'],
                'cache_count' => empty($cacheTimes['cache_count'])?0:$cacheTimes['cache_count']], '1');
        } catch (\Exception $exception) {
            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' 系统发生异常,错误信息为：' . $exception->getMessage());
            return returnJosn([], '200000');
        }
    }

    //浏览 增加用户玩游戏次数
    public function viewgoods(Request $request)
    {
        try {
            $userId = $request->param('user_id');
            if (empty($userId)) return returnJosn([], '100001');
            $playNum = new BtPlayNum();
            $now = time();
            $signinCache = $this->redis->exitsKey(self::STRING_USER_VIEW_GOOD.date('Y-m-d').':' . $userId);
            $userInfoId = $playNum->where('user_id', $userId)
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->value('id');
            if (empty($signinCache)) {
                $this->redis->setBtViewGood($userId);
                if (empty($userInfoId)) {
                    $this->addplaynum($userId);
                } else {
                    $this->saveplaynum($userId);
                }
                $cacheTimes = $this->redis->getBtViewGood($userId);
                return returnJosn(['cache_time' => $cacheTimes['cache_time'],
                    'cache_count'=>$cacheTimes['cache_count']], '1');

            } else {
                $userCacheInfo = $this->redis->getBtViewGood($userId);

                $cacheHour =  $userCacheInfo['cache_time'];
                $cacheCount = $userCacheInfo['cache_count'];
                $diffTime =ceil(($now - $cacheHour)/3600);
                if ($diffTime > 2 && $cacheCount < 2) {
                    $this->saveplaynum($userId);
                    $this->redis->userViewCount($userId);
                    $newInfo=$this->redis->getBtViewGood($userId);
                    return returnJosn(['cache_time' => $userCacheInfo['cache_time'],
                        'cache_count'=>$newInfo['cache_count']], '1');
                } else {
                    return returnJosn(['cache_time' => $userCacheInfo['cache_time'],
                        'cache_count'=>$userCacheInfo['cache_count']], '100017');
                }
            }

        } catch (\Exception $exception) {
            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' 系统发生异常,错误信息为：' . $exception->getMessage());
            return returnJosn([], '200000');
        }
    }


    //分享 被分享者正式玩游戏后  增加分享者玩游戏次数

    public function shareusers(Request $request)
    {
        try {
            $shareUserId = $request->param('share_user_id');
            $userId = $request->param('user_id');
            $os = $request->param('os');
            $devId = $request->param('uuid');
            $os=empty($os)?'wechat':$os;
            $devId=empty($devId)?'wechat':$devId;
            if (empty($shareUserId) || empty($userId) || empty($os) || empty($devId))
                return returnJosn([], '100007');

            $mShare = new BtShare();
            $havaShareUserInfo=$mShare->where('user_id',$userId)
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->where('share_user_id',$shareUserId)
                ->value('do_type');
            if($shareUserId==$userId||$havaShareUserInfo==2){
                return returnJosn([],1);
            }

            //防止重复分享增加次数
            $havaShare=$mShare->where('share_user_id',$shareUserId)
                ->where('user_id',$userId)
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->where('do_type',1)->value('id');
            if(!empty($havaShare)){
                return returnJosn([],'100018');
            }

            //判断被分享者是否为新客

            $host = config('api.API_URL_JAVA_MIDDLE');
            $url = $host . '/user/getNewCustomerType?user_id=' . $userId .
                '&os=' . $os . '&dev_id=' . $devId;
            $userStatus = json_decode(httpGet($url), true);
            // 0非新客 1，2都是新客
            if ($userStatus['result'] == 1 && !empty($userStatus['type'])) {
                $playNum = new BtPlayNum();

                $userInfo = $playNum->where('user_id', $shareUserId)
                    ->where('create_time','>=',$this->cycleStart)
                    ->where('create_time','<=',$this->cycleEnd)
                    ->value('id');
                if (empty($userInfo)) {
                    $this->addplaynum($shareUserId);
                } else {
                    $this->saveplaynum($shareUserId);
                }

                $mShare->insert([
                    'create_time' => date('Y-m-d'),
                    'user_id' => $userId,
                    'share_user_id' => $shareUserId,
                    'add_time' => time(),
                    'do_type' => 1,
                ]);
            }

            return returnJosn([], 1);
        } catch (\Exception $exception) {
            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' 系统发生异常,错误信息为：' . $exception->getMessage());
            return returnJosn([], '200000');
        }
    }


    //注册 给用户增加游戏次数 java回调地址
    //todo 待调试
    public function regusers()
    {
        try {
            $mjsonInfo = file_get_contents("php://input");
            Log::info('注册回调数据,时间：'.date('Y-m-d H:i:s').'数据为：'.$mjsonInfo);
            $ajsonInfo = json_decode($mjsonInfo, true);
            $data = [
                'share_user_id' => $ajsonInfo['e_user_id'],
                'user_id' => $ajsonInfo['user_id'],
                'create_time' => date('Y-m-d'),
                'add_time' => time(),
                'do_type' => 2,
            ];
            $mShare = new BtShare();
            $mShare->insert($data);

            $mPlayNum = new BtPlayNum();
            $userInfoId = $mPlayNum->where('user_id', $ajsonInfo['e_user_id'])
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->value('id');
            if (empty($userInfoId)) {
                $this->addplaynum($ajsonInfo['e_user_id']);
            } else {
                $this->saveplaynum($ajsonInfo['e_user_id']);
            }
            echo 'ok';exit;
        } catch (\Exception $exception) {
            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' 系统发生异常,错误信息为：' . $exception->getMessage());
            return returnJosn([], '200000');
        }
    }


    //添加用户玩游戏记录-返回用户所占百分比
    public function getuserper(Request $request)
    {
        try {

            $userId = $request->param('user_id');
            $shareUserId = $request->param('share_user_id');
            //新老客信息
            $os = $request->param('os');
            $devId = $request->param('uuid');
            $os = empty($os) ? 'wechat' : $os;
            $devId = empty($devId) ? 'wechat' : $devId;
            if (empty($userId) && empty($shareUserId)) return returnJosn([], '100001');
            $userType = ($userId==$shareUserId) ? 1 : 2;//1分享者 团长 2 被分享者 团员 需要判断是否下单
            $newUserId = ($userId==$shareUserId) ? $shareUserId : $userId;

            $money = $request->param('num_money');
            $playType = $request->param('play_type', self::PLAY_GAME_TRY_TYPE);

            //判断用户是否有游戏机会
            $playnum = $this->userplaynum($newUserId);
            if ($playnum < 1) return returnJosn([], '100013');

            $playLog = new BtPlayLog();
            $playLog->insert([
                'user_id' => $newUserId,
                'num_money' => $money,
                'play_type' => $playType,
                'create_time' => date('Y-m-d'),
                'add_time' => time(),
            ]);

            //分享者正式玩游戏，开团 ，次数减一
            $mShare=new BtShare();
            if ($playType == self::PLAY_GAME_FORMAL_TYPE) {

                if ($userType == 1) {
                    $byshareInfo = $mShare->where('user_id', $shareUserId)
                        ->where('create_time','>=',$this->cycleStart)
                        ->where('create_time','<=',$this->cycleEnd)
                        ->value('id');
                    if (empty($byshareInfo)) {
                        $this->useraddgroup($newUserId, $money);
                    }
                }
                if ($userType == 2) {
                    $host = config('api.API_URL_JAVA_MIDDLE');
                    $url = $host . '/user/getNewCustomerType?user_id=' . $userId .
                        '&os=' . $os . '&dev_id=' . $devId;
                    $userStatus = json_decode(httpGet($url), true);
                    if(empty($userStatus['type'])){
                        $this->useraddgroup($userId, $money);
                    }
                }
                $this->decplaynum($userId);
            }

            $userper = '0%';
            if ($playType == self::PLAY_GAME_FORMAL_TYPE) {
//                $arrMoney = $playLog->where('play_type', self::PLAY_GAME_FORMAL_TYPE)
//                    ->where('create_time','>=',$this->cycleStart)
//                    ->where('create_time','<=',$this->cycleEnd)
//                    ->column('num_money');
//                asort($arrMoney);
//                $arrMoney=array_values($arrMoney);
//                $count=count($arrMoney);
//                $key=array_search($money,$arrMoney)+1;
//                $userper = round($key / $count * 100) . '%';
                $userper=(mt_rand(10, 40)).'%';
                return returnJosn(['percent' => $userper], 1);
            }
            return returnJosn([],1);

        } catch (\Exception $exception) {
            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' 系统发生异常,错误信息为：' . $exception->getMessage());
            return returnJosn([], '200000');
        }

    }

    //获取当前用户可玩游戏次数
    public function getusernum(Request $request)
    {

        try {
            $userId = $request->param('user_id');
            if (empty($userId)) return returnJosn([], '100001');
            $mPlayNum = new BtPlayNum();
            $res = $mPlayNum->where('user_id', $userId)
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->sum('play_num');
            if($res<=0){
                $res=0;
            }
            return returnJosn(['num' => $res], 1);

        } catch (\Exception $exception) {
            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' 系统发生异常,错误信息为：' . $exception->getMessage());
            return returnJosn([], '200000');
        }

    }

    //分享者玩游戏  开团
    public function useraddgroup($shareUserId, $money)
    {
        try {
            $group = new BtUserGroup();
            $detail = new BtGroupDetail();
            $userId = $shareUserId;

            $garr = [
                'user_id' => $userId,
                'status' => 1,
            ];

            $detailInfo = $detail->where($garr)
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->value('id');

            if (empty($detailInfo)) {
                Db::startTrans();
                $garr['add_time']=time();
                $garr['create_time']=date('Y-m-d');
                $groupId = $group->insertGetId($garr);
                $darr = [
                    'group_id' => $groupId,
                    'user_id' => $userId,
                    'num_money' => $money,
                    'status' => 1,
                    'create_time' => date('Y-m-d'),
                ];
                $detail->insert($darr);
                Db::commit();
            }

        } catch (\Exception $exception) {
            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' 系统发生异常,错误信息为：' . $exception->getMessage());
            Db::rollback();
        }

    }

    //正式数钱页面 第一名数据接口
//    public function getfirstinfo(Request $request)
//    {
//
//        try {
//            $userId = $request->param('user_id');
//            if (empty($userId)) return returnJosn([], '100001');
//            $detail = new BtGroupDetail();
//            $groupId = $detail->where('user_id', $userId)
//                ->where('status', 1)
//                ->where('create_time','>=',$this->cycleStart)
//                ->where('create_time','<=',$this->cycleEnd)
////                ->where('create_time', date('Y-m-d'))
//                ->value('group_id');
//
//            //不在团中，取当日正式玩游戏最大值
//            if(empty($groupId)){
//              $test=\db('bt_play_log')
//                  ->where('create_time','>=',$this->cycleStart)
//                  ->where('create_time','<=',$this->cycleEnd)
////                  ->where('create_time',date('Y-m-d'))
//                  ->where('play_type',2)
//                  ->field('sum(num_money)')
//                  ->group('user_id')
//                  ->select();
//                $MAXmoney=0;
//              if(!empty($test)){
//                  $MAXmoney=max($test)['sum(num_money)'];
//              }
//
//              $ownMoneyInfo=\db('bt_play_log')
//                  ->field('sum(num_money)')
//                  ->where('create_time','>=',$this->cycleStart)
//                  ->where('create_time','<=',$this->cycleEnd)
////                  ->where('create_time',date('Y-m-d'))
//                  ->where('play_type',2)->where('user_id',$userId)
//                  ->select();
//                $ownMoney=0;
//              if(!empty($ownMoneyInfo)){
//                  $ownMoney=max($ownMoneyInfo)['sum(num_money)'];
//              }
//              return returnJosn(['max_num'=>empty($MAXmoney)?$this->maxNum:$MAXmoney,
//                  'own_money'=>$ownMoney],1);
//            }
//            //取当前团中数钱最大的数据
//            $userids = $detail->where('group_id', $groupId)
//                ->where('create_time','>=',$this->cycleStart)
//                ->where('create_time','<=',$this->cycleEnd)
////                ->where('create_time', date('Y-m-d'))
//                ->where('status', 1)->column('user_id');
//            if(empty($userids))
//                return returnJosn(['max_num' => $this->maxNum,'own_money'=>0], 1);
//            $dayMaxMoneyInfo=\db('bt_play_log')
//                ->field('sum(num_money)')
//                ->where('create_time','>=',$this->cycleStart)
//                ->where('create_time','<=',$this->cycleEnd)
////                ->where('create_time',date('Y-m-d'))
//                ->where('user_id','in',$userids)->where('play_type',2)
//                ->group('user_id')
//                ->select();
//        $dayMaxMoney=0;
//            if(!empty($dayMaxMoneyInfo)){
//                $dayMaxMoney=max($dayMaxMoneyInfo)['sum(num_money)'];
//            }
//        $ownMoneyInfo=\db('bt_play_log')
//            ->field('sum(num_money)')
//            ->where('create_time','>=',$this->cycleStart)
//            ->where('create_time','<=',$this->cycleEnd)
////            ->where('create_time',date('Y-m-d'))
//            ->where('play_type',2)->where('user_id',$userId)
//            ->select();
//        $ownMoney=0;
//            if(!empty($ownMoneyInfo)){
//                $ownMoney=max($ownMoneyInfo)['sum(num_money)'];
//            }
//             return returnJosn(['max_num'=>empty($dayMaxMoney)?$this->maxNum:$dayMaxMoney,
//                 'own_money'=>$ownMoney],1);
//        } catch (\Exception $exception) {
//            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' 系统发生异常,错误信息为：' . $exception->getMessage());
//            return returnJosn([], '200000');
//        }
//    }

    //正式数钱页面 第一名数据接口
    public function getfirstinfo(Request $request)
    {
        try{
            $userId = $request->param('user_id');
            if (empty($userId)) return returnJosn([], '100001');
            $mplaylog=new BtPlayLog();
            $ownMoneyInfo=$mplaylog
                ::where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->where('play_type',2)->where('user_id',$userId)
                ->sum('num_money');
            $ownMoney=0;
            if(!empty($ownMoneyInfo)){
                $ownMoney=$ownMoneyInfo;
            }
            return returnJosn(['max_num'=>'10000',
                'own_money'=>$ownMoney],1);
        }catch (\Exception $exception){
            return returnJosn([], '200000');
        }

//        return returnJosn(['max_num'=>'10000',
//                    'own_money'=>'100'],1);
//        try {
//            $userId = $request->param('user_id');
//            if (empty($userId)) return returnJosn([], '100001');
//            $detail = new BtGroupDetail();
//            $groupId = $detail::where('user_id', $userId)
//                ->where('create_time','>=',$this->cycleStart)
//                ->where('create_time','<=',$this->cycleEnd)
//                ->value('group_id');
//            //不在团中，取当日正式玩游戏最大值
//              $mplaylog=new BtPlayLog();
//            if(empty($groupId)){
//                $test=$mplaylog
//                    ::where('create_time','>=',$this->cycleStart)
//                    ->where('create_time','<=',$this->cycleEnd)
//                    ->where('play_type',2)
//                    ->group('user_id')
//                    ->order('tp_sum','desc')
//                    ->sum('num_money');
//
//                $MAXmoney=0;
//                if(!empty($test)){
//                    $MAXmoney=$test;
//                }
//
//                $ownMoneyInfo=$mplaylog
//                    ::where('create_time','>=',$this->cycleStart)
//                    ->where('create_time','<=',$this->cycleEnd)
//                    ->where('play_type',2)->where('user_id',$userId)
//                    ->order('tp_sum','desc')
//                    ->sum('num_money');
//                $ownMoney=0;
//                if(!empty($ownMoneyInfo)){
//                    $ownMoney=$ownMoneyInfo;
//                }
//                return returnJosn(['max_num'=>empty($MAXmoney)?$this->maxNum:$MAXmoney,
//                    'own_money'=>$ownMoney],1);
//            }else{
//                //取当前团中数钱最大的数据
//                $userids = $detail->where('group_id', $groupId)
//                    ->where('create_time','>=',$this->cycleStart)
//                    ->where('create_time','<=',$this->cycleEnd)
//                    ->where('status', 1)->column('user_id');
//                if(empty($userids))
//                    return returnJosn(['max_num' => $this->maxNum,'own_money'=>0], 1);
//                $dayMaxMoneyInfo=$mplaylog
//                    ::where('create_time','>=',$this->cycleStart)
//                    ->where('create_time','<=',$this->cycleEnd)
//                    ->where('user_id','in',$userids)->where('play_type',2)
//                    ->group('user_id')
//                    ->order('tp_sum','desc')
//                    ->sum('num_money');
//                $dayMaxMoney=0;
//                if(!empty($dayMaxMoneyInfo)){
//                    $dayMaxMoney=$dayMaxMoneyInfo;
//                }
//                $ownMoneyInfo=$mplaylog
//                    ::where('create_time','>=',$this->cycleStart)
//                    ->where('create_time','<=',$this->cycleEnd)
//                    ->where('play_type',2)->where('user_id',$userId)
//                    ->sum('num_money');
//                $ownMoney=0;
//                if(!empty($ownMoneyInfo)){
//                    $ownMoney=$ownMoneyInfo;
//                }
//                return returnJosn(['max_num'=>empty($dayMaxMoney)?$this->maxNum:$dayMaxMoney,
//                    'own_money'=>$ownMoney],1);
//            }
//
//        } catch (\Exception $exception) {
//            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' 系统发生异常,错误信息为：' . $exception->getMessage());
//            return returnJosn([], '200000');
//        }
    }

    //活动首页 我的团队排名
    public function getgroupsort(Request $request)
    {

        try {
            $userId = $request->param('user_id');
            if (empty($userId)) return returnJosn(['cycle_end'=>strtotime($this->cycleEnd.'23:59:59')], '100001');
            $detail = new BtGroupDetail();
            $groupId = $detail->where('user_id', $userId)
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->value('group_id');
            if (empty($groupId))
                return returnJosn(['cycle_end'=>strtotime($this->cycleEnd.'23:59:59')], '100009');
            $arr = [
                'group_id' => $groupId,
                'status' => 1,
            ];
            $group = new BtGroupDetail();
            $info = $group->where($arr)
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->field('id,user_id,group_id')
                ->select();
            if (empty($info))
                return returnJosn([], 1);
            $info = $info->toArray();
            $auid = array_column($info, 'user_id');
            $maxMoney=\db('bt_play_log')
                ->field('user_id,sum(num_money)')
                ->group('user_id')
                ->where('play_type',2)
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->where('user_id','in',$auid)
                ->select();
            $suid = implode(',', $auid);
            //获取用户昵称 头像
            $host = config('api.API_URL_JAVA_MIDDLE') . '/user/getInfoInBulk?user_ids=';
            $param = $suid . '&fields=nickname,avatar,username';
            $url = $host . $param;
            $muserinfo = httpGet($url);
            $auserinfo = json_decode($muserinfo, true);
            if ($auserinfo['result'] == 1 && isset($auserinfo['users'])) {
                $auser = $auserinfo['users'];
                foreach ($info as $k => $v) {
                    foreach ($auser as $ku => $vu) {
                        if ($v['user_id'] == $vu['userId']) {
                            $info[$k]['nickname'] = empty($vu['nickname'])?substr_replace($vu['username'], '****', 3, 4):$vu['nickname'];
                            $info[$k]['avatar'] = $vu['avatar'];
                        }
                    }
                    foreach ($maxMoney as $mk=>$mv){
                        !isset($info[$k]['num_money']) && $info[$k]['num_money']=0;
                        if($v['user_id']==$mv['user_id']){
                            $info[$k]['num_money']=$mv['sum(num_money)'];
                        }
                    }
                }
            }
            $sortMoneyInfo = array_column($info,'num_money');
            array_multisort($sortMoneyInfo,SORT_DESC,$info);
            $data['list']=$info;
            //周期内截止的时间
            $data['cycle_end']=strtotime($this->cycleEnd.'23:59:59');
            return returnJosn($data, '1');
        } catch (\Exception $exception) {
            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' 系统发生异常,错误信息为：' . $exception->getMessage());
            return returnJosn([], '200000');
        }
    }

    //活动首页滚动数据-假数据
    public function titleinfo()
    {
        try {
            $random = mt_rand(0, 40);
            $mTitle = new BtTitleInfo();
            $info = $mTitle->where('id','>','100')->limit($random, 50)->select();
            return returnJosn($info, 1);
        } catch (\Exception $exception) {
            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' 系统发生异常,错误信息为：' . $exception->getMessage());
            return returnJosn([], '200000');
        }
    }

    //当前用户今天属于哪个团
    public function getUserGroup(Request $request)
    {
        try {
            $userId = $request->param('user_id');
            if (empty($userId)) return returnJosn([], '100001');
            $os = $request->param('os');
            $devId = $request->param('uuid');
            $os=empty($os)?'wechat':$os;
            $devId=empty($devId)?'wechat':$devId;
            $host = config('api.API_URL_JAVA_MIDDLE');
            $url = $host . '/user/getNewCustomerType?user_id=' . $userId .
                '&os=' . $os . '&dev_id=' . $devId;
            $userStatus = json_decode(httpGet($url), true);
            $detail = new BtGroupDetail();
            $groupId = $detail->where('user_id', $userId)
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->value('group_id');
            $groupCount=$detail->where('group_id', $groupId)
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->count('id');
            //0 老客 1新客
            return returnJosn(['group_id' => $groupId,'group_count'=>$groupCount,'user_type'=>$userStatus['type']], 1);
        } catch (\Exception $exception) {
            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' 系统发生异常,错误信息为：' . $exception->getMessage());
            return returnJosn([], '200000');
        }
    }

    //被分享者下单成功  参团  todo java回调地址 待联调
    public function joingroup()
    {

        try {
            $mInfo = file_get_contents("php://input");
            Log::info('支付回调数据,时间：'.date('Y-m-d H:i:s').'数据为：'.$mInfo);
            $aInfo = json_decode($mInfo, true);


            //今日分享信息
            $mShare = new BtShare();
            $mShareAll=$mShare
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->field('share_user_id,user_id')->select();
            if(empty($mShareAll)){
                echo 'ok';exit;
            }
            $aShareall=$mShareAll->toArray();
            $allInfo=$this->getgroupid($aShareall,$aInfo['user_id']);


            //被分享者是否被邀请
            $shareUserId = $mShare->where('user_id', $aInfo['user_id'])->value('share_user_id');
            if (empty($shareUserId)){
                echo 'ok';exit;
            }

            if(empty($allInfo)){
                echo 'ok';exit;
            }
            //分享者今天是否 开团 -截止被分享者下单的时刻
            $mGroup = new BtUserGroup();
            $groupId = $mGroup->where('user_id','in', $allInfo)->where('status', 1)
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->value('id');
            if (empty($groupId)){
                echo 'ok';exit;
            }
            //被分享者今天是否正式玩过游戏
//            $mPlayLog = new BtPlayLog();
//            $userPlay = $mPlayLog->where('user_id', $aInfo['user_id'])->where('play_type', self::PLAY_GAME_FORMAL_TYPE)
//                ->where('create_time','>=',$this->cycleStart)
//                ->where('create_time','<=',$this->cycleEnd)
//                ->findOrEmpty()->toArray();
//            if (empty($userPlay)){
//                echo 'ok';exit;
//            }

            $mDetail = new BtGroupDetail();
            //下过单的不插入数据
            $orderinfo=$mDetail->where('user_id', $aInfo['user_id'])
                ->where('create_time','>=',$this->cycleStart)
                ->where('create_time','<=',$this->cycleEnd)
                ->value('id');
            if(!empty($orderinfo)){
                echo 'ok';exit;
            }
            DB::startTrans();
            $data = [
                'user_id' => $aInfo['user_id'],
                'order_id' => isset($aInfo['order_id'])?$aInfo['order_id']:0,
                'pay_time' => isset($aInfo['pay_time'])?$aInfo['pay_time']:0,
                'group_id' => $groupId,
                'status' => 1,
                'num_money' => empty($userPlay['num_money'])?100:$userPlay['num_money'],
                'create_time'=>date('Y-m-d')
            ];
            $mDetail = new BtGroupDetail();
            $mDetail->insert($data);
            $payArr=[
                'user_id' => $aInfo['user_id'],
                'order_id' => isset($aInfo['order_id'])?$aInfo['order_id']:0,
                'create_time'=>date('Y-m-d'),
                'amount'=>$aInfo['amount'],
                'add_time'=>time(),
                'order_no'=>$aInfo['order_no'],
            ];
            $mUserPay=new BtUserPay();
            $mUserPay->insert($payArr);
            Db::commit();
            echo 'ok';exit;
        } catch (\Exception $exception) {
            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' 系统发生异常,错误信息为：' . $exception->getMessage());
            Db::rollback();
            return returnJosn([], '200000');
        }
    }

  protected function getgroupid($data,$pid)
  {
     static $arr = [];
      foreach ($data as $k => $v) {
          if ($v['user_id'] == $pid) {
              $arr[] = $v['share_user_id'];
              unset($data[$k]);
              $this->getgroupid($data, $v['share_user_id']);
          }
      }
      return $arr;
  }


  public function test()
  {
     if(true){
         echo 111;
     }else{
         echo 2222;
     }
  }

}
