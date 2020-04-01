<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-04-09
 * Time: 17:32
 */

namespace app\bt\controller;

use think\App;
use think\Controller;
use app\bt\model\BtPlayNum;
use app\bt\model\BtUserGroup;
use app\bt\model\BtGroupDetail;
use think\facade\Log;
use app\bt\model\Blackredis;
use app\zhuanzhuan\model\MRedis;
use app\bt\model\BtUserPay;
use app\bt\model\BtPlayLog;

class Batchinfo extends Controller
{
    //每日奖金总额
    const AWARD_COUNT = 40000;
    //百团大战 奖金Key
    const STRING_BTDD_TODAY_AWARD = 'string_btdd_today_award:';

    //团队奖金配置  人数=》奖金
    protected $groupAward = [
        '2'=>'5',
        '3' => '15',
//        '5' => '30',
        '10' => '50',
        '50' => '300',
        '100' => '1000',
    ];

    //团人数
    protected $perponCount=array(2,3,10,50,100);

    //团奖金数据 推送给java topic
    protected $topic='zz_bt_group_money';
//

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

    public function createinfo(){

//        db('bt_user_group')->insert([
//            'create_time'=>date('Y-m-d'),
//            'user_id'=>88887777,
//            'status'=>1,
//            'add_time'=>time(),
//        ]);
//        $count=4;
//        for ($i=1;$i<=$count;$i++){
//            db('bt_group_detail')->insert([
//                'create_time'=>date('Y-m-d'),
//                'user_id'=>'5566'.$i,
//                'status'=>1,
//                'num_money'=>4000,
//                'group_id'=>'221',
//            ]);
//            db('bt_play_log')->insert([
//                'create_time'=>date('Y-m-d'),
//                'user_id'=>'5566'.$i,
//                'play_type'=>2,
//                'num_money'=>4000,
//                'add_time'=>time(),
//            ]);
//            db('bt_play_num')->insert([
//                'create_time'=>date('Y-m-d'),
//                'user_id'=>'5566'.$i,
//                'play_num'=>5,
//                'add_time'=>time(),
//            ]);
//            db('bt_share')->insert([
//                'create_time'=>date('Y-m-d'),
//                'user_id'=>'5566'.$i,
//                'share_user_id'=>88887777,
//                'do_type'=>2
//            ]);
//        }
    }

    //用户玩游戏次数清零处理  每天凌晨一点执行 不需要处理
    public function clearusernum()
    {

        //用户次数清零
        try {
            $mPlayNum = new BtPlayNum();
            $yester = date('Y-m-d', strtotime("-1 day"));
            $mPlayNum->where('create_time', $yester)
                ->update(['play_num' => 0]);
            Log::error('用户玩游戏次数清零处理正常执行: ' . date('Y-m-d H:i:s'));
            echo 'suc';
            exit;
        } catch (\Exception $exception) {
            Log::error('用户玩游戏次数清零处理发生异常: ' . date('Y-m-d H:i:s'));
            echo 'error';
            exit;
        }
    }


    public function testinfo()
    {
        $info=db('bt_title_info')->find();
//        if(!empty($info)){
//            exit;
//        }
        for ($i = 1; $i <= 100; $i++) {
            $randomTime = mt_rand(0, 60);
            $randomMoney = mt_rand(0.5, 20) * 10;
            $name = $this->getChar();
            $data = [
                'cinfo' => '用户 ' . $name . '**在 ' . $randomTime . "秒提现了" . $randomMoney . '元',
            ];
            db('bt_title_info')->insert($data);
        }
    }

    function getChar()  // $num为生成汉字的数量

    {
        $num = 1;
        $b = '';
        for ($i = 0; $i < $num; $i++) {
            // 使用chr()函数拼接双字节汉字，前一个chr()为高位字节，后一个为低位字节
            $a = chr(mt_rand(0xB0, 0xD0)) . chr(mt_rand(0xA1, 0xF0));
            // 转码
            $b .= iconv('GB2312', 'UTF-8', $a);
        }
        return $b;
    }

    //统计有效团id
    public function getgroupmoneys()
    {
        set_time_limit(0);
        //周期奖金时间判断
         if(date('Y-m-d 00:00:00')==config('btinfo.cycle_start_time')||$this->validTime!=3){
             Log::info('不在周期性发奖金的时间');
             exit('error-cycle-time');
         }
         //增加时间判断
        $dateH=date('H');
        if($dateH<=8){
            Log::info('没有到达脚本执行时间'.date('Y-m-d H:i:s'));
            exit('error time');
        }

        //查询昨天2人以上的团
        $groupIds = $this->threegroupInfo();
        if (empty($groupIds))
            return returnJosn([], '100015');
        //黑名单过滤
        $filterIds = $this->filtergroup($groupIds);
        if (empty($filterIds))
            return returnJosn([], '100016');
        //有效团每个人分的奖金
        $mRedis = new MRedis();
        //获取昨天奖金金额
        $dayMoney = $mRedis->getBtAward(date('Y-m-d'));
        if (!empty($dayMoney) && $dayMoney >= self::AWARD_COUNT)
            return returnJosn([], 1);

        $topic=$this->topic;
        $javaQueue=config('api.JAVA_QUEUE_HOST').'/message/recive-batch';

        foreach ($filterIds as $mk => $mv) {
            //当前团属于那档，可以分到钱数
            $tuanMoney = $this->filegroup($mv['count_group_id']);
            //今日总奖金还剩多少余额
            $odd = self::AWARD_COUNT - $dayMoney;
            if ($odd < $tuanMoney) {
                break;
            } else {
              $everyGoupMoney=$this->perponMoney($mv['group_id'],$tuanMoney);
                //todo 调java队列
                $queueDATA=[
                  'business'=>$topic,
                  'message'=>json_encode($everyGoupMoney),
                ];

                Log::info('团奖金，请求java接口，url为：'.$javaQueue.'请求参数为：'.json_encode($queueDATA));
                $jres=httpPost($javaQueue,$queueDATA,'1');
                Log::info('团奖金接口返回信息：'.$jres);
                $ajres=json_decode($jres,true);
                if(!empty($ajres['code'])){
                    Log::info('团奖金接口返回错误，错误信息为：'.$jres);
                    break;
                }
                //今日团奖金放入缓存 现在每日限额
                $mRedis->setBtAward($tuanMoney);
                Log::info('在时间 '.date('Y-m-d H:i:s').'的团Id<'.$mv['group_id'].
                    '团奖励金额为:'.json_encode($everyGoupMoney).'团总奖金为：'.$tuanMoney);
                //团置为无效
                $this->invaildDetail($mv['group_id']);

            }
      //  sleep(1);
        }
        return returnJosn([], 1);
    }

    //团队中每个人分钱处理
    protected function perponMoney($groupId, $tuanMoney)
    {
      $mDetail=new BtGroupDetail();
      $mGroup=new BtUserGroup();
      $ids=$mDetail
//          ->where('create_time',date('Y-m-d',strtotime("-1 day")))
          ->where('create_time','>=', date('Y-m-d', strtotime("-3 day")))
          ->where('create_time','<=', date('Y-m-d', strtotime("-1 day")))
          ->where('group_id',$groupId)
          ->where('status',1)
          ->column('user_id');

      $mPlay=new BtPlayLog();
      $playInfo=$mPlay->where('user_id','in',$ids)
//          ->where('create_time',date('Y-m-d',strtotime("-1 day")))
          ->where('create_time','>=', date('Y-m-d', strtotime("-3 day")))
          ->where('create_time','<=', date('Y-m-d', strtotime("-1 day")))
          ->field('user_id,sum(num_money) as num_money')
          ->group('user_id')->select()->toArray();
        array_multisort(array_column($playInfo,'num_money'),SORT_DESC,$playInfo);
//      $personMoney[$ids[0]]=intval($tuanMoney)*0.6;
        $tuanzhangId=$mGroup->where('id',$groupId)
//            ->where('create_time',date('Y-m-d',strtotime("-1 day")))
            ->where('create_time','>=', date('Y-m-d', strtotime("-3 day")))
            ->where('create_time','<=', date('Y-m-d', strtotime("-1 day")))
            ->value('user_id');
      $firstAmount=intval($tuanMoney)*0.6;
      $firtId=$playInfo[0]['user_id'];
      $shengyu=$tuanMoney-$firstAmount;
      $tuanKey=array_search($firtId,$ids);
      unset($ids[$tuanKey]);
      $num=count($ids);
      $info=$this->randBonus($shengyu,$num,array_values($ids),$tuanzhangId);
      $firstOrder=$this->firstmoney($firtId);
        $arr[0]['user_id']=$firtId;
        $arr[0]['amount']=$firstAmount;
        $arr[0]['order_id']=$firstOrder['order_id'];
        $arr[0]['trade_no']=$firtId.date('Ymd');
        array_walk($arr,function($item) use (&$info) {
            array_unshift($info, $item);
        });
      return $info;
    }

   //随机分红包
    protected function randBonus($total, $count,$ids,$tuanzhangId)
    {
        $input = range(0.5, $total, 1);
        $olds=$ids;
        $rand_keys = (array)array_rand($input, empty($count - 1)?1:$count - 1);
        $last = 0;
        foreach ($rand_keys as $i => $key) {
            $current = $input[$key] - $last;
            $items[$ids[$i]] = $current;
            $last = $input[$key];
        }
        $items[] = $total-array_sum($items);
        $keys=$olds;
        $vals=array_values($items);
        $arr=[];

        $host=config('api.QUIT_ORDER_HOST');
        //1：存在退款 2:不存在退款
        foreach ($keys as $k=>$v){

            $orderMoney=$this->firstmoney($v);
            if(!empty($orderMoney['order_no'])){
                $url=$host.'?order_no='.$orderMoney['order_no'];
                $infos=httpGet($url);
                if($infos==1) continue;
                $arr[$k]['user_id']=$v;
                if($v==$tuanzhangId){
                    $arr[$k]['amount']=$vals[$k];
                    $arr[$k]['order_id']=$orderMoney['order_id'];
                }else{
                    if($vals[$k]<=$orderMoney['amount']){
                        $arr[$k]['amount']=$vals[$k];
                    }else{
                        $realMoney=$orderMoney['amount'];
                        $arr[$k]['amount']=$realMoney;
                    }
                    $arr[$k]['order_id']=$orderMoney['order_id'];
                }
                $arr[$k]['trade_no']=$v.date('Ymd');
            }else{
                $arr[$k]['user_id']=$v;
                $arr[$k]['amount']=$vals[$k];
                $arr[$k]['order_id']=0;
                $arr[$k]['trade_no']=$v.date('Ymd');
            }

        }
        return $arr;
    }

    //获取团员首单金额
    protected function firstmoney($userId)
    {
        $mPay=new BtUserPay();
        $res=$mPay->where('user_id',$userId)
//            ->where('create_time',date('Y-m-d',strtotime("-1 day")))
            ->where('create_time','>=', date('Y-m-d', strtotime("-3 day")))
            ->where('create_time','<=', date('Y-m-d', strtotime("-1 day")))
            ->field('order_id,amount,order_no')->find();
        if(empty($res))return ['amount'=>0,'order_id'=>0,'order_no'=>''];
        $info=$res->toArray();
        return ['amount'=>$res['amount'],'order_id'=>$info['order_id'],'order_no'=>$res['order_no']];
    }

    //团划到有效的归档里
    protected function filegroup($count)
    {
        Log::info('当前团员总数为：'.$count);
        if (array_key_exists($count, $this->groupAward)) {
            return $this->groupAward[$count];
        }else{
            $keys = $this->perponCount;
            array_push($keys,$count);
            asort($keys);
            $info = array_values($keys);
            $res = array_search($count, $info);
            $newKeys = $keys[$res - 1];
            return $this->groupAward[$newKeys];
        }
    }

    //获取2人以上的团
    protected function threegroupInfo()
    {
        $detail = new BtGroupDetail();
        $groupIds = $detail::field('group_id,count(group_id) as count_group_id')
            ->where('create_time','>=', date('Y-m-d', strtotime("-3 day")))
            ->where('create_time','<=', date('Y-m-d', strtotime("-1 day")))
            ->where('status',1)
            ->group('group_id')->having('count(group_id)>=2')
            ->select()->toArray();
        return $groupIds;
    }

    //todo 黑名单待沟通联调
    //调用风控数据 过滤团中黑名单团id
    protected function filtergroup($groupIds)
    {
        $blackRedis = new Blackredis();
        foreach ($groupIds as $k => $v) {
            $blackInfo = $blackRedis->getBtBlackList($v['group_id']);
            if (!empty($blackInfo)) {
                unset($groupIds[$k]);
            }
        }
        return $groupIds;
    }

    //生成唯一标示
    protected function randomkeys($length){
        $code = '';
        for ($i=1;$i<$length;$i++) {         //通过循环指定长度
            $randcode = mt_rand(0,9);     //指定为数字
            $code .= $randcode;
        }

        return $code;
    }


    //团分完钱，团置为无效
    protected function invaildDetail($groupId)
    {
        $detail=new BtGroupDetail();
        $info=$detail::where('group_id',$groupId)
//            ->where('create_time',date('Y-m-d',strtotime("-1 day")))
            ->where('create_time','>=', date('Y-m-d', strtotime("-3 day")))
            ->where('create_time','<=', date('Y-m-d', strtotime("-1 day")))
            ->update(['status'=>2]);
        return $info;
    }
}