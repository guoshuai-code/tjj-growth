<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-05-22
 * Time: 14:40
 */

namespace app\activity\controller;

use think\App;
use think\Controller;
use think\facade\Log;
use think\Request;
use app\activity\service\ActivityService;
use app\activity\model\Activityredis;

//header("Access-Control-Allow-Origin: *");
//header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
//header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS,PATCH');

/**
 * Class Activity
 * @package app\activity\controller
 * 618活动
 */
class Activity extends Controller
{

    //活动截止时间
    protected $activityEndTime = '2019-06-21 23:59:59';

    protected $service = '';

    protected $activityId = 1;//618活动

    protected $page = 1;//默认第一页

    protected $size = 60;//默认每页60条 后台显示

    protected $detailsize = 10; //默认显示10条 万券页面列表

    const DAY_COUNT_AWARD = 8888;

    //tab name
    protected $tabNames = [
        '' => 80,
        '精选推荐' => 1,
        '潮流服饰' => 2,
        '家居百货' => 3,
        '时尚鞋包' => 4,
        '美妆个护' => 5,
        '休闲美食' => 6,
        '3C生活' => 7,
    ];

    protected $taoNameList = [
        'xiaodi' => 't_xiaodi',
        'xiake' => 't_xiake',
        'boshi' => 't_boshi',
        'dafu' => 't_dafu',
        'baobao' => 't_baobao',
    ];

    //配置抽奖概率
    protected $YhqList = [
        '0' => array('id' => 1, 'title' => '一等奖88元优惠券', 'v' => 15),
        '1' => array('id' => 2, 'title' => '二等奖66元优惠券', 'v' => 20),
        '2' => array('id' => 3, 'title' => '三等奖33元优惠券', 'v' => 65),
//        '3' => array('id' => 4, 'title' => '特等奖华为P30Pro', 'v' => 0),
    ];

    //todo id需要更换为线上id
    protected $awardMatch = [
        '1' => 'fullCoupon~145,fullCoupon~145,fullCoupon~146,fullCoupon~147',//一等奖优惠券Id
        '2' => 'fullCoupon~148,fullCoupon~148,fullCoupon~149,fullCoupon~150',//二等奖优惠券Id
        '3' => 'fullCoupon~148,fullCoupon~151,fullCoupon~146',//三等奖优惠券Id
    ];

    public function __construct(App $app = null)
    {
        if (strtolower($_SERVER['REQUEST_METHOD']) == 'options') {
            exit;
        }
        parent::__construct($app);
        $this->service = new ActivityService();
    }

    /**
     * @return false|string
     * 主会场 活动时间
     */
    public function deadline()
    {
        try {
            $arr = [
                'curr_time' => time(),
                'activity_end_time' => strtotime($this->activityEndTime),
            ];
            return returnTjjJson($arr, 1, 200, 0);
        } catch (\Exception $exception) {
            Log::info(__CLASS__ . '/' . __FUNCTION__ . ':' . $exception->getMessage());
            return returnTjjJson([], -1, 500, 0);
        }
    }

    //后台接口 查询tab列表信息
    public function gettabinfo(Request $request)
    {
        try {
            $activityId = $request->param('params.activity_id');
            !$activityId && $activityId = $this->activityId;
            $id = $request->param('params.id');
            $res = $this->service->mGetTab($activityId, $id);
            $arrs['count'] = 0;
            $arrs['list'] = $res['data'];
            if ($res !== false) return returnTjjJson($arrs, 1, 200, 0);
            return returnTjjJson([], -1, 500, 0);
        } catch (\Exception $exception) {
            Log::info(__CLASS__ . '/' . __FUNCTION__ . ':' . $exception->getMessage());
            return returnTjjJson([], -1, 500, 0);
        }
    }

    //后台接口 添加tab信息
    public function addtabinfo(Request $request)
    {
        try {
            $tabName = $request->param('params.tab_name');
            $tabSort = $request->param('params.tab_sort');
            $havaTab = $this->service->havaTabInfo($tabSort, $tabName);
            if (!empty($havaTab))
                return json([
                    'data' => [],
                    'result' => -1,
                    'message' => 'tab的名称或者排序值已经存在',
                    'subCode' => 200,
                    'realMessage' => '',
                ]);
            $arr = [
                'tab_name' => $tabName,
                'tab_sort' => $tabSort,
                'activity_id' => $this->activityId,
            ];
            if (empty($tabName) || empty($tabSort))
                return json([
                    'data' => [],
                    'result' => -1,
                    'message' => 'tab的名称或者排序值不能为空',
                    'subCode' => 200,
                    'realMessage' => '',
                ]);
            $res = $this->service->mAddTab($arr);
            if ($res !== false) return returnTjjJson(['res' => $res], 1, 200, 0);
            return returnTjjJson([], -1, 500, 0);
        } catch (\Exception $exception) {
            Log::info(__CLASS__ . '/' . __FUNCTION__ . ':' . $exception->getMessage());
            return returnTjjJson([], -1, 500, 0);
        }
    }

    //后台接口 编辑tab信息
    public function edittabinfo(Request $request)
    {
        try {
            $aParam = $request->param();
            $arr = $aParam['params'];
            if (!isset($arr['id']) || empty($arr['id']))
                return json([
                    'data' => [],
                    'result' => -1,
                    'message' => 'id不能为空',
                    'subCode' => 200,
                    'realMessage' => '',
                ]);
            $id = $arr['id'];

            unset($arr['id']);
            if (isset($arr['tab_name']) || isset($arr['tab_sort'])) {
                $havaName = $this->service->getIdTabInfo($id, $arr['tab_name'], $arr['tab_sort']);
                if (!empty($havaName['havaname'])||!empty($havaName['havasort'])) return json([
                    'data' => [],
                    'result' => -1,
                    'message' => 'tab的名称或者排序值已经存在',
                    'subCode' => 200,
                    'realMessage' => '',
                ]);
            }

            $res = $this->service->mEditTab($id, $arr);
            if ($res !== false) return returnTjjJson([], 1, 200, 0);
            return returnTjjJson([], -1, 500, 0);
        } catch (\Exception $exception) {
            Log::info(__CLASS__ . '/' . __FUNCTION__ . ':' . $exception->getMessage());
            return returnTjjJson([], -1, 500, 0);
        }
    }

    //后台接口 删除tab信息
    public function deltabinfo(Request $request)
    {
        try {
            $id = $request->param('params.id');
            if (empty($id)) return returnTjjJson([], 1, 200, 1);
            $res = $this->service->mDelTab($id);
            if ($res !== false) return returnTjjJson(['type' => $res], 1, 200, 0);
            return returnTjjJson([], -1, 500, 0);
        } catch (\Exception $exception) {
            Log::info(__CLASS__ . '/' . __FUNCTION__ . ':' . $exception->getMessage());
            return returnTjjJson([], -1, 500, 0);
        }
    }

    //后台接口 查询detail信息
    public function getdetail(Request $request)
    {
        try {
            $activityId = $request->param('params.activity_id');
            $page = $request->param('params.page');
            $size = $request->param('params.size');
            $goodsId = $request->param('params.goods_id');
            $couponId = $request->param('params.coupon_id');
            $id = $request->param('params.id');
            !$activityId && $activityId = $this->activityId;
            !$page && $page = $this->page;
            !$size && $size = $this->size;
            $arr = [
                'goods_id' => $goodsId,
                'activity_id' => $activityId,
                'coupon_id' => $couponId,
            ];
            $start = ($page - 1) * $size;
            $res = $this->service->mGetDetail($arr, $start, $size, $id);
            $arrs['count'] = $res['count'];
            $arrs['list'] = $res['data'];
            if ($res !== false) return returnTjjJson($arrs, 1, 200, 0);
            return returnTjjJson([], -1, 500, 0);
        } catch (\Exception $exception) {
            Log::info(__CLASS__ . '/' . __FUNCTION__ . ':' . $exception->getMessage());
            return returnTjjJson([], -1, 500, 0);
        }
    }

    //后台接口 批量添加detail信息
    public function adddetail(Request $request)
    {
        try {

            $param = $request->param();
            $arr = $param['params'];
            $requestTabName = array_unique(array_column($arr, 'tab_name'));
            $dbTabName = $this->service->getTabInfo();
            $dbTabName = array_flip($dbTabName);
            $tabArr = [];
            foreach ($requestTabName as $k => $v) {
                if (!array_key_exists($v, $dbTabName)) {
                    $tabArr[] = $v;
                }
            }
            $sTabArr = implode(',', $tabArr);
            if (!empty($tabArr)) return json([
                'data' => [],
                'result' => -1,
                'message' => 'tab的名称' . $sTabArr . '不存在',
                'subCode' => 200,
                'realMessage' => '',
            ]);
            $res = $this->service->mAddDetail($arr);
            if ($res !== false) return returnTjjJson([], 1, 200, 0);
            return returnTjjJson([], -1, 500, 0);
        } catch (\Exception $exception) {
            Log::info(__CLASS__ . '/' . __FUNCTION__ . ':' . $exception->getMessage());
            return returnTjjJson([], -1, 500, 0);
        }
    }

    //后台接口 编辑接口
    public function editdetail(Request $request)
    {
        try {

            $aParam = $request->param();
            $arr = $aParam['params'];
            if (!isset($arr['id']) || empty($arr['id']))
                return json([
                    'data' => [],
                    'result' => -1,
                    'message' => 'id不能为空',
                    'subCode' => 200,
                    'realMessage' => '',
                ]);
            $id = $arr['id'];
            unset($arr['id']);
            $res = $this->service->mEditDetail($id, $arr);
            if ($res !== false) return returnTjjJson([], 1, 200, 0);
            return returnTjjJson([], -1, 500, 0);
        } catch (\Exception $exception) {
            Log::info(__CLASS__ . '/' . __FUNCTION__ . ':' . $exception->getMessage());
            return returnTjjJson([], -1, 500, 0);
        }
    }

    //后台接口 删除detail信息
    public function deldetail(Request $request)
    {
        try {
            $aParam = $request->param();
            $arr = $aParam['params'];
            if (!isset($arr['id']) || empty($arr['id']))
                return json([
                    'data' => [],
                    'result' => -1,
                    'message' => 'id不能为空',
                    'subCode' => 200,
                    'realMessage' => '',
                ]);
            $id = $arr['id'];
            if (empty($id)) return returnTjjJson([], 1, 200, 1);
            $res = $this->service->mDelDetail($id);
            if ($res !== false) return returnTjjJson([], 1, 200, 0);
            return returnTjjJson([], -1, 500, 0);
        } catch (\Exception $exception) {
            Log::info(__CLASS__ . '/' . __FUNCTION__ . ':' . $exception->getMessage());
            return returnTjjJson([], -1, 500, 0);
        }
    }


    //前端接口 万券齐发列表接口
    public function getcouponlist(Request $request)
    {
        try {
            $userId = $request->param('user_id');
            $mRedis = new Activityredis();

            $tabName = $request->param('tab_name');
            if (empty($tabName)) {
                $tabName = '精选推荐';
            }
            if (array_key_exists($tabName, $this->tabNames)) {
                $tabId = $this->tabNames[$tabName];
            } else {
                $tabId = 66;
            }
            $page = $request->param('page');
            $size = $request->param('size');
            !$page && $page = $this->page;//默认第一页
            !$size && $size = $this->detailsize;//默认每页条数
            $start = ($page - 1) * $size;

            //查询后台配置商品信息
            $res = $this->service->mDayGoods($tabName, $start, $size);

            if (empty($res['data']))
                return returnTjjJson([], 1, 200, 0);
            $goodsIds = array_column($res['data'], 'goods_id');

            //查询商品属性信息
            $cacheGoods = $mRedis->getgoodsinfo($tabId, $page);
            if (!empty($cacheGoods)) {
                $aGoodInfo = json_decode($cacheGoods, true);
            } else {
                $aGoodInfo = $this->service->getgoodsInfo($goodsIds);
                if (empty($aGoodInfo))
                    return returnTjjJson([], 1, 200, 0);
                $mRedis->setgoodsinfo($aGoodInfo, $tabId, $page);
            }

            //组装排序字段
            foreach ($aGoodInfo as $kt => $kv) {
                foreach ($res['data'] as $kr => $vr) {
                    if ($kv['goods_id'] == $vr['goods_id']) {
                        $aGoodInfo[$kt]['goods_sort'] = $vr['goods_sort'];
                    }
                }
            }

            $acouponIds = array_column($res['data'], 'coupon_id');
            $sCouponIds = implode(',', $acouponIds);

            //获取优惠券信息
            $backendApiUrl = config('bkapi.backend_api') . 'storeCouponList';
            $url = $backendApiUrl . '?couponId=' . $sCouponIds . '&user_id=' . $userId;
            $mCounponInfo = httpGet($url);
            Log::info('请求店铺优惠券列表接口,请求信息为: ' . $url . '! 返回新为 ' . $mCounponInfo);
            $counponInfo = json_decode($mCounponInfo, true);
            if ($counponInfo['result'] != 1 || empty($counponInfo['data']) || empty($counponInfo)) {
                $resConponInfo = [];
                foreach ($aGoodInfo as $ak => $av) {
                    $aGoodInfo[$ak]['coupon']['count'] = '';
                    $aGoodInfo[$ak]['coupon']['amount'] = '';
                    $aGoodInfo[$ak]['coupon']['status'] = 10;
                }
            } else {
                $resConponInfo = $counponInfo['data'];
                foreach ($aGoodInfo as $k => $v) {
                    foreach ($res['data'] as $rk => $rv) {
                        if ($v['goods_id'] == $rv['goods_id']) {
                            $aGoodInfo[$k]['coupon_id'] = $rv['coupon_id'];
                        }
                    }
                }
                foreach ($aGoodInfo as $ak => $av) {
                    $aGoodInfo[$ak]['coupon']['count'] = '';
                    $aGoodInfo[$ak]['coupon']['amount'] = '';
                    $aGoodInfo[$ak]['coupon']['status'] = 10;
                    foreach ($resConponInfo as $ks => $vs) {
                        if ($av['coupon_id'] == $vs['couponId']) {
                            $aGoodInfo[$ak]['coupon']['count'] = $vs['couponStock'] - $vs['useCouponNum'];//库存
                            $aGoodInfo[$ak]['coupon']['amount'] = $vs['couponAmount'];//面额
                            $aGoodInfo[$ak]['coupon']['status'] = $vs['curUserReceive'];//0未领取，1已领取
                        }
                    }
                }

            }
            $sortField = array_column($aGoodInfo, 'goods_sort');
            array_multisort($sortField, SORT_ASC, $aGoodInfo);
            $datainfo['list'] = $aGoodInfo;
            $datainfo['count'] = $res['count'];
            $datainfo['page'] = $page;
            $datainfo['size'] = $size;
            return returnTjjJson($datainfo, 1, 200, 0);
        } catch (\Exception $exception) {
            Log::info(__CLASS__ . '/' . __FUNCTION__ . ':' . $exception->getMessage());
            return returnTjjJson([], -1, 500, 0);
        }
    }

    //下单回调数据 生成吉祥物
    public function getorderinfo()
    {
        echo 'ok';exit;
        try {
            $mInfo = file_get_contents("php://input");
            Log::info('618活动支付回调,时间：' . date('Y-m-d H:i:s') . '数据为：' . $mInfo);
            $aInfo = json_decode($mInfo, true);
            $order = $aInfo['order'];
            $userId = $order['user_id'];
            if (empty($userId)) {
                echo 'ok';
                exit;
            }
            //一元拼团
            if (isset($order['coin']) && $order['coin'] == 1) {
                Log::info('用户ID ' . $userId . ' 的订单类型为: 一元拼团 ,订单ID为 ' . $order['order_id']);
                echo 'ok';
                exit;
            }
            //新人全额返
            if (isset($order['order_tag']) && $order['order_tag'] == 'signInCashBack') {
                Log::info('用户ID ' . $userId . ' 的订单类型为: 新人全额返,订单ID为 ' . $order['order_id']);
                echo 'ok';
                exit;
            }

            $mascotName = $this->getmascot();
            $filedName = $this->taoNameList[$mascotName];
            $mRedis = new Activityredis();
            $mRedis->lpushMascot($userId, $filedName);
            $res = $this->service->mAddmascot($userId, $filedName);
            if (!empty($res)) {
                echo 'ok';
                exit;
            } else {
                Log::info('回调产生的数据,插入表失败');
                echo false;
                exit;
            }
        } catch (\Exception $exception) {
            Log::info(__CLASS__ . '/' . __FUNCTION__ . ':' . $exception->getMessage());
            return returnTjjJson([], -1, 500, 0);
        }
    }

    //领取店铺优惠券接口
    public function getcouponinfo(Request $request)
    {
        try {
            $userId = $request->param('user_id');
            $couponId = $request->param('coupon_id');
            $uuid = $request->param('uuid');
            $token = $request->param('token');
            if (empty($couponId) || empty($userId) || empty($uuid) || empty($token))
                return returnTjjJson([], 100007, 200, 1);
            //校验用户身份信息
            $tokenUrl = config('bkapi.domain_java_token') . '?user_id=' . $userId . '&uuid=' . $uuid . '&token=' . $token . '&app_resource=0';
            $muserType = httpGet($tokenUrl);
            $auserType = json_decode($muserType, true);
            if (empty($auserType['result'])) return returnTjjJson([],100022);

            $host = config('bkapi.backend_api') . 'receiveStoreCoupon';
            $url = $host . '?user_id=' . $userId . '&couponId=' . $couponId;
            $res = httpGet($url);
            Log::info('领取店铺优惠券接口，请求信息为: ' . $url . '!返回信息为: ' . $res);
            $data = json_decode($res, true);
            // result 1领取成功 ，0领取失败
            if ($data['result'] == 1){
                return returnTjjJson(['type'=>1],1);
            }elseif ($data['result'] == '-2001'){
                return returnTjjJson(['type'=>-2001],1);
            }elseif ($data['result'] == '-2002'){
                return returnTjjJson(['type'=>-2002],1);
            }elseif ($data['result'] == '-2003'){
                return returnTjjJson(['type'=>-2003],1);
            }elseif ($data['result'] == '-2004'){
                return returnTjjJson(['type'=>-2004],1);
            }else{
                return returnTjjJson(['type'=>0],-1);
            }
        } catch (\Exception $exception) {
            return returnTjjJson(['type'=>0],200000);
        }
    }


    //生成吉祥物接口 $orderId 非活动Id

    /**
     *
     * xiaodi 概率20%
     * xiake  概率20%
     * boshi 概率20%
     * dafu 概率20%
     * baobao 概率20%
     */
    protected function getmascot()
    {
        $mascotId = mt_rand(1, 5);
        $mascotName = '';
        if ($mascotId == 1) {
            $mascotName = 'xiaodi';
        } elseif ($mascotId == 2) {
            $mascotName = 'xiake';
        } elseif ($mascotId == 3) {
            $mascotName = 'boshi';
        } elseif ($mascotId == 4) {
            $mascotName = 'dafu';
        } else {
            $mascotName = 'baobao';
        }
        return $mascotName;
    }


    //用户吉祥物次数信息接口
    public function getusercount(Request $request)
    {
        try {
            $userId = $request->param('user_id');
            if (empty($userId)) return returnTjjJson([], 1, 200, 1);
            //用户进到页面 主动给一次
            $mRedis = new Activityredis();
            $exists=$mRedis->gefreettaoinfo($userId);
            if(empty($exists)){
                $mascotName = $this->getmascot();
                $filedName = $this->taoNameList[$mascotName];
                $this->service->mAddmascot($userId, $filedName);
                $mRedis->setfreetaoinfo($userId);
                $mRedis->lpushMascot($userId, $filedName);
            }

            $res = $this->service->mGetCount($userId);
            if (empty($res)) {
                $res['t_xiaodi'] = '';
                $res['t_xiake'] = '';
                $res['t_boshi'] = '';
                $res['t_dafu'] = '';
                $res['t_baobao'] = '';
            }
            $lrange = $mRedis->lrangeMascot($userId);
            $detailInfo=[];
            //过滤到白名单期间产生的数据
            $startTime="2019-06-11 00:00:00";
            $uStrat=strtotime($startTime);
            if(!empty($lrange)){
                foreach ($lrange as $k=>$v){
                     $times=substr($v,strpos($v,',')+1);
                     if($times>=$uStrat){
                         $detailInfo[]=substr($v,0,strpos($v, ','));
                     }
                }
            }

            //拼装数据
            $data['list'] = $res; //吉祥物的个数
            $data['detail'] = array_count_values($detailInfo); //谈吉祥物的次数
            $data['user_id'] = $userId;
            $mRedis->delKeys($userId);
            return returnTjjJson($data, 1, 200);
        } catch (\Exception $exception) {
            Log::info(__CLASS__ . '/' . __FUNCTION__ . ':' . $exception->getMessage());
            return returnTjjJson([], -1, 500, 0);
        }
    }

    //用户抽奖接口
    public function getdraw(Request $request)
    {
        try {
            $userId = $request->param('user_id');
            $uuid = $request->param('uuid');
            $token = $request->param('token');

            if (empty($userId) || empty($uuid) || empty($token))
                return returnTjjJson([],100007);
            //验证用户身份
            $tokenUrl = config('bkapi.domain_java_token') . '?user_id=' . $userId . '&uuid=' . $uuid . '&token=' . $token . '&app_resource=0';
            $muserType = httpGet($tokenUrl);
            $auserType = json_decode($muserType, true);
            if (empty($auserType['result'])) return returnTjjJson([],100022);
            //判断今日是否超过限额
            $mRedis = new Activityredis();
            $dayCount = $mRedis->getmascotDay(date('Y-m-d'));
            if ($dayCount > self::DAY_COUNT_AWARD) return returnTjjJson([], 100021);
            //判断用户是否满足抽奖条件
            $info = $this->service->drawInfo($userId);
            if (empty($info)) return returnTjjJson([], 100019);
            foreach ($this->YhqList as $k => $v) {
                $arr[$v['id']] = $v['v'];
            }
            //抽中的奖品
            $awardType = $this->get_rand($arr);
            //给用户更新用户优惠券信息--平台优惠券

            $resParams = '?user_id=' . $userId . '&stringCoupon=' . $this->awardMatch[$awardType];
            $url = config('bkapi.pingtai_api_url');
            $mgetCoupon = httpGet($url . $resParams);
            Log::info('领取平台优惠券接口，请求信息为：' . $url . $resParams . '!返回信息为:' . $mgetCoupon);
            $aGetCoupon = json_decode($mgetCoupon, true);
            //result==1请求成功
            if ($aGetCoupon['result'] == 1) {
                //减少用户信息
                $this->service->decUserInfo($userId);
                //增加每日优惠券限额
                $mRedis->incrmascot(date('Y-m-d'));
                Log::info('用户：' . $userId . '在时间: ' . date('Y-m-d H:i:s') . ' 抽到 ' . $awardType . ' 等奖');
                return returnTjjJson(['award_info' => $awardType], 1, 200);
            }
            return returnTjjJson(['award_info' => ''], 100024, 200);

        } catch (\Exception $exception) {
            Log::info(__CLASS__ . '/' . __FUNCTION__ . ':' . $exception->getMessage());
            return returnTjjJson([], -1, 500, 0);
        }
    }


    /**
     * 中奖概率
     * @param $proArr
     * @return int|string
     */
    public function get_rand($proArr)
    {
        $result = '';
        //概率数组的总概率精度
        $proSum = array_sum($proArr);
        //概率数组循环
        foreach ($proArr as $key => $proCur) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $proCur) {
                $result = $key;
                break;
            } else {
                $proSum -= $proCur;
            }
        }
        unset ($proArr);
        return $result;
    }

}