<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-05-06
 * Time: 19:11
 */

namespace app\activity\controller;

use think\App;
use think\Controller;
use app\activity\model\Activityredis;
use app\activity\model\Goods;
use app\activity\model\Imgfavs;
use app\activity\model\Spec;
use think\Request;
use think\facade\Request as SRequest;

//header("Access-Control-Allow-Origin: *");
//header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
//header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS,PATCH');

class Index extends Controller
{
    /**
     * @var Goods
     */
    protected $mGoods;

    /**
     * @var Imgfavs
     */
    protected $mImgfavs;

    /**
     * @var Spec
     */
    protected $mSpec;

    /**
     * @var Activityredis
     */
    protected $mRedis;

    /**
     * @var false|string
     */
    protected $nowTime;

    /**
     * @var array
     * type关系映射
     */
    protected $reqMap=[
       '1'=>'1',
       '2'=>'2',
       '3'=>'3',
       '4'=>'4',
    ];

    protected $hourMap=[
        '8'=>'1',
        '12'=>'2',
        '16'=>'3',
        '20'=>'4',
    ];

    protected $hourMapss=[
        '1'=>'8',
        '2'=>'12',
        '3'=>'16',
        '4'=>'20',
    ];
    public function __construct(App $app = null)
    {
        if (strtolower($_SERVER['REQUEST_METHOD']) == 'options') {
            exit;
        }
        parent::__construct($app);
        $this->mRedis = new Activityredis();
        $this->mGoods = new Goods();
        $this->mImgfavs = new Imgfavs();
        $this->mSpec = new Spec();
        $this->nowTime = date('Y-m-d');
    }

  protected  function array_unset_tt($arr,$key='goods_id'){
      //建立一个目标数组
      $res = array();
      foreach ($arr as $value) {
          //查看有没有重复项
          if(isset($res[$value[$key]])){
              unset($value[$key]);  //有：销毁
          }else{
              $res[$value[$key]] = $value;
          }
      }
      return $res;
  }


    /**
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 家纺好时光 舒适健康活动接口
     */
    public function homegoodtime()
    {
        try {

            $mredis = $this->mRedis->getgoodtime();
            if (empty($mredis)) {
                $dayInfos = config('acid.good_time_activity_time');
                if (array_key_exists($this->nowTime, $dayInfos)) {
                    $aids = $dayInfos[$this->nowTime];
                } else {
                    $aids = reset($dayInfos);
                }
                //goods信息
                $mgoodsInfo = $this->mGoods->where('goods_id', 'in', $aids)
                    ->where('status',3)
                    ->where('state','<>',4)
                    ->field('goods_id,goods_name')->select();
                if (empty($mgoodsInfo)) {
                    return returnJosn([], 1);
                }
                $aGoodsInfo = $mgoodsInfo->toArray();
                // spec信息
                $oldmSpedInfo = $this->mSpec->where('goods_id', 'in', $aids)
                    ->where('is_putaway',1)
                    ->field('min(group_price) as group_price,shop_price,goods_id,stocknum as num')
                    ->group('goods_id')
                    ->select()->toArray();


                $mSpedInfo = $this->mSpec->where('goods_id', 'in', $aids)
                    ->where('is_putaway',1)
                    ->where('stocknum','>',0)
                    ->field('group_price,shop_price,goods_id,stocknum as num')
                    ->order('group_price','asc')
                    ->select()->toArray();
                $mSpedInfo=$this->array_unset_tt($mSpedInfo,'goods_id');

                //imgfavs
                $mImgfavs = $this->mImgfavs->where('goods_id', 'in', $aids)
                    ->where('is_cover', 1)
                    ->field('goods_id,img320_url,img640_url')->select()->toArray();
                foreach ($aGoodsInfo as $k => $v) {
                    $aGoodsInfo[$k]['num'] = 0;
                    foreach ($mSpedInfo as $sk => $sv) {
                        if ($v['goods_id'] == $sv['goods_id']) {
                            if(isset($sv['group_price'])&&!empty($sv['group_price'])){
                                $aGoodsInfo[$k]['group_price'] = $sv['group_price'];
                            }
                            if(isset($sv['shop_price'])&&!empty($sv['shop_price'])){
                                $aGoodsInfo[$k]['shop_price'] = $sv['shop_price'];
                            }
                            if(isset($sv['num'])&&!empty($sv['num'])){
                                $aGoodsInfo[$k]['num'] = $sv['num'];
                            }else{
                                $aGoodsInfo[$k]['num'] = 0;
                            }

                        }
                    }

                    foreach ($mImgfavs as $km => $vm) {
                        if ($v['goods_id'] == $vm['goods_id']) {
                            if(isset($vm['img320_url'])&&!empty($vm['img320_url'])){
                                $aGoodsInfo[$k]['img320_url'] = $vm['img320_url'];
                            }
                            if(isset($vm['img640_url'])&&!empty($vm['img640_url'])){
                                $aGoodsInfo[$k]['img640_url'] = $vm['img640_url'];
                            }

                        }
                    }

                    if(!isset($aGoodsInfo[$k]['group_price'])){
                     foreach ($oldmSpedInfo as $olk=>$olv){
                         if ($v['goods_id']==$olv['goods_id']){
                             $aGoodsInfo[$k]['group_price']=$olv['group_price'];
                             $aGoodsInfo[$k]['shop_price']=$olv['shop_price'];
                         }
                     }
                    }

                }
                $this->mRedis->setgoodtime($aGoodsInfo);
                return returnJosn($aGoodsInfo, 1);
            } else {
                return returnJosn(json_decode($mredis,true), 1);
            }
        } catch (\Exception $exception) {
            return returnJosn([], '20000');
        }

    }


    /**
     * @return false|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 浓浓果园
     */
    public function gardeninfo()
    {
        try {
            $mredis = $this->mRedis->getgardeninfo();
            if(empty($mredis)){
                $dayInfos = config('acid.garden_activity_time');
                if (array_key_exists($this->nowTime, $dayInfos)) {
                    $aids = $dayInfos[$this->nowTime];
                } else {
                    $aids = reset($dayInfos);
                }
                //goods信息
                $mgoodsInfo = $this->mGoods->where('goods_id', 'in', $aids)
                    ->where('status',3)
                    ->where('state','<>',4)
                    ->field('goods_id,goods_name')->select();
                if (empty($mgoodsInfo)) {
                    return returnJosn([], 1);
                }
                $aGoodsInfo = $mgoodsInfo->toArray();
                // spec信息
                $oldmSpedInfo = $this->mSpec->where('goods_id', 'in', $aids)
                    ->where('is_putaway',1)
                    ->field('min(group_price) as group_price,shop_price,goods_id,stocknum as num')
                    ->group('goods_id')
                    ->select()->toArray();

                $mSpedInfo = $this->mSpec->where('goods_id', 'in', $aids)
                    ->where('is_putaway',1)
                    ->where('stocknum','>',0)
                    ->field('group_price,shop_price,goods_id,stocknum as num')
                    ->order('group_price','asc')
                    ->select()->toArray();
                $mSpedInfo=$this->array_unset_tt($mSpedInfo,'goods_id');
                //imgfavs
                $mImgfavs = $this->mImgfavs->where('goods_id', 'in', $aids)
                    ->where('is_cover', 1)
                    ->field('goods_id,img320_url,img640_url')->select()->toArray();
                foreach ($aGoodsInfo as $k => $v) {
                    $aGoodsInfo[$k]['num'] = 0;
                    foreach ($mSpedInfo as $sk => $sv) {
                        if ($v['goods_id'] == $sv['goods_id']) {
                            if(isset($sv['group_price'])&&!empty($sv['group_price'])){
                                $aGoodsInfo[$k]['group_price'] = $sv['group_price'];
                            }
                            if(isset($sv['shop_price'])&&!empty($sv['shop_price'])){
                                $aGoodsInfo[$k]['shop_price'] = $sv['shop_price'];
                            }
                            if(isset($sv['num'])&&!empty($sv['num'])){
                                $aGoodsInfo[$k]['num'] = $sv['num'];
                            }else{
                                $aGoodsInfo[$k]['num'] = 0;
                            }

                        }
                    }

                    foreach ($mImgfavs as $km => $vm) {
                        if ($v['goods_id'] == $vm['goods_id']) {
                            if(isset($vm['img320_url'])&&!empty($vm['img320_url'])){
                                $aGoodsInfo[$k]['img320_url'] = $vm['img320_url'];
                            }
                            if(isset($vm['img640_url'])&&!empty($vm['img640_url'])){
                                $aGoodsInfo[$k]['img640_url'] = $vm['img640_url'];
                            }

                        }
                    }
                    if(!isset($aGoodsInfo[$k]['group_price'])){
                        foreach ($oldmSpedInfo as $olk=>$olv){
                            if ($v['goods_id']==$olv['goods_id']){
                                $aGoodsInfo[$k]['group_price']=$olv['group_price'];
                                $aGoodsInfo[$k]['shop_price']=$olv['shop_price'];
                            }
                        }
                    }
                }
                $this->mRedis->setgardeninfo($aGoodsInfo);
                return returnJosn($aGoodsInfo, 1);
            }else{
                return returnJosn(json_decode($mredis,true), 1);
            }

        } catch (\Exception $exception) {
            return returnJosn([], 20000);
        }

    }

    /**
     * @return false|string
     * 炎炎夏日
     */
    public function summerinfo()
    {
        try {
            $mredis = $this->mRedis->getsummerinfo();
            if(empty($mredis)){
                $dayInfos = config('acid.summer_activity_time');
                if (array_key_exists($this->nowTime, $dayInfos)) {
                    $aids = $dayInfos[$this->nowTime];
                } else {
                    $aids = reset($dayInfos);
                }
                //goods信息
                $mgoodsInfo = $this->mGoods->where('goods_id', 'in', $aids)
                    ->where('status',3)
                    ->where('state','<>',4)
                    ->field('goods_id,goods_name')->select();
                if (empty($mgoodsInfo)) {
                    return returnJosn([], 1);
                }
                $aGoodsInfo = $mgoodsInfo->toArray();
                // spec信息
                $oldmSpedInfo = $this->mSpec->where('goods_id', 'in', $aids)
                    ->where('is_putaway',1)
                    ->field('min(group_price) as group_price,shop_price,goods_id,stocknum as num')
                    ->group('goods_id')
                    ->select()->toArray();

                $mSpedInfo = $this->mSpec->where('goods_id', 'in', $aids)
                    ->where('is_putaway',1)
                    ->where('stocknum','>',0)
                    ->field('group_price,shop_price,goods_id,stocknum as num')
                    ->order('group_price','asc')
                    ->select()->toArray();
                $mSpedInfo=$this->array_unset_tt($mSpedInfo,'goods_id');
                //imgfavs
                $mImgfavs = $this->mImgfavs->where('goods_id', 'in', $aids)
                    ->where('is_cover', 1)
                    ->field('goods_id,img320_url,img640_url')->select()->toArray();
                foreach ($aGoodsInfo as $k => $v) {
                    $aGoodsInfo[$k]['num'] = 0;
                    foreach ($mSpedInfo as $sk => $sv) {
                        if ($v['goods_id'] == $sv['goods_id']) {
                            if(isset($sv['group_price'])&&!empty($sv['group_price'])){
                                $aGoodsInfo[$k]['group_price'] = $sv['group_price'];
                            }
                            if(isset($sv['shop_price'])&&!empty($sv['shop_price'])){
                                $aGoodsInfo[$k]['shop_price'] = $sv['shop_price'];
                            }
                            if(isset($sv['num'])&&!empty($sv['num'])){
                                $aGoodsInfo[$k]['num'] = $sv['num'];
                            }else{
                                $aGoodsInfo[$k]['num'] = 0;
                            }

                        }
                    }

                    foreach ($mImgfavs as $km => $vm) {
                        if ($v['goods_id'] == $vm['goods_id']) {
                            if(isset($vm['img320_url'])&&!empty($vm['img320_url'])){
                                $aGoodsInfo[$k]['img320_url'] = $vm['img320_url'];
                            }
                            if(isset($vm['img640_url'])&&!empty($vm['img640_url'])){
                                $aGoodsInfo[$k]['img640_url'] = $vm['img640_url'];
                            }

                        }
                    }
                    if(!isset($aGoodsInfo[$k]['group_price'])){
                        foreach ($oldmSpedInfo as $olk=>$olv){
                            if ($v['goods_id']==$olv['goods_id']){
                                $aGoodsInfo[$k]['group_price']=$olv['group_price'];
                                $aGoodsInfo[$k]['shop_price']=$olv['shop_price'];
                            }
                        }
                    }
                }
                $this->mRedis->setsummerinfo($aGoodsInfo);
                return returnJosn($aGoodsInfo, 1);
            }else{
                return returnJosn(json_decode($mredis,true),1);
            }

        } catch (\Exception $exception) {
            return returnJosn([], '20000');
        }
    }


    public function foreverlove(Request $request)
    {
      try{
          // 0=>未开始  1=>8 2=>12 3=>16 4=>20
          $type=$request->param('type',1);
          $nowHour=date('H');
          //当前时间段可以购买的
          $resType=$this->vaildHour($nowHour);
          //当前查看的信息
          if(empty($type)){
              $type=$this->vaildHour($nowHour);
          }
          $cacheInfo=$this->mRedis->getloveinfo($type);
          if(empty($cacheInfo)){
              $dateInfo=config('acid.love_activity_ids');
              $nowTime=date('Y-m-d');
              if(array_key_exists($nowTime,$dateInfo)){
                  $aids=$dateInfo[$nowTime][$type];
              }else{
                  $aids=reset($dateInfo)[$type];
              }

              //goods信息
              $mgoodsInfo = $this->mGoods->where('goods_id', 'in', $aids)
                  ->where('status',3)
                  ->where('state','<>',4)
                  ->field('goods_id,goods_name')->select();
              if (empty($mgoodsInfo)) {
                  return returnJosn([], 1);
              }
              $aGoodsInfo = $mgoodsInfo->toArray();
              // spec信息
              $oldmSpedInfo = $this->mSpec->where('goods_id', 'in', $aids)
                  ->where('is_putaway',1)
                  ->field('min(group_price) as group_price,shop_price,goods_id,stocknum as num')
                  ->group('goods_id')
                  ->select()->toArray();

              $mSpedInfo = $this->mSpec->where('goods_id', 'in', $aids)
                  ->where('is_putaway',1)
                  ->where('stocknum','>',0)
                  ->field('group_price,shop_price,goods_id,stocknum as num')
                  ->order('group_price','asc')
                  ->select()->toArray();
              $mSpedInfo=$this->array_unset_tt($mSpedInfo,'goods_id');
              //imgfavs
              $mImgfavs = $this->mImgfavs->where('goods_id', 'in', $aids)
                  ->where('is_cover', 1)
                  ->field('goods_id,img320_url,img640_url')->select()->toArray();

              foreach ($aGoodsInfo as $k => $v) {
                  $aGoodsInfo[$k]['num'] = 0;
                  foreach ($mSpedInfo as $sk => $sv) {
                      if ($v['goods_id'] == $sv['goods_id']) {
                          if(isset($sv['group_price'])&&!empty($sv['group_price'])){
                              $aGoodsInfo[$k]['group_price'] = $sv['group_price'];
                          }
                          if(isset($sv['shop_price'])&&!empty($sv['shop_price'])){
                              $aGoodsInfo[$k]['shop_price'] = $sv['shop_price'];
                          }
                          if(isset($sv['num'])&&!empty($sv['num'])){
                              $aGoodsInfo[$k]['num'] = $sv['num'];
                          }else{
                              $aGoodsInfo[$k]['num'] = 0;
                          }

                      }
                  }

                  foreach ($mImgfavs as $km => $vm) {
                      if ($v['goods_id'] == $vm['goods_id']) {
                          if(isset($vm['img320_url'])&&!empty($vm['img320_url'])){
                              $aGoodsInfo[$k]['img320_url'] = $vm['img320_url'];
                          }
                          if(isset($vm['img640_url'])&&!empty($vm['img640_url'])){
                              $aGoodsInfo[$k]['img640_url'] = $vm['img640_url'];
                          }

                      }
                  }
                  if(!isset($aGoodsInfo[$k]['group_price'])){
                      foreach ($oldmSpedInfo as $olk=>$olv){
                          if ($v['goods_id']==$olv['goods_id']){
                              $aGoodsInfo[$k]['group_price']=$olv['group_price'];
                              $aGoodsInfo[$k]['shop_price']=$olv['shop_price'];
                          }
                      }
                  }
                  $buy=$this->hourMapss[$resType];
                  $ymd=date('Y-m-d');
                  $newtime=$ymd.' '.$buy.':00:00';
                  if(time()<strtotime($newtime)||$type>$resType){
                      unset($aGoodsInfo[$k]['goods_id']);
                  }
              }
              $data['list']=$aGoodsInfo;
              $data['type']=$type; //请求时间段的type
              $data['currentType']=$resType;//当前时间段type
              $this->mRedis->setloveinfo($data,$type);
              return returnJosn($data,1);
          }else{
             return returnJosn(json_decode($cacheInfo,true),1);
          }

      }catch (\Exception $exception){
          return returnJosn([], '200000');
      }

    }



    //当前时间归到特定档
    protected function vaildHour($nowHour)
    {
        if ($nowHour >= 8 && $nowHour < 12) {
            return $this->hourMap[8];
        } elseif ($nowHour >= 12 && $nowHour < 16) {
            return $this->hourMap[12];
        } elseif ($nowHour >= 16 && $nowHour < 20) {
            return $this->hourMap[16];
        } elseif ($nowHour >= 20) {
            return $this->hourMap[20];
        } else {
            return $this->hourMap[8];
        }

    }

    public function childinfo()
    {
        try {
            $mredis = $this->mRedis->getchildinfo();
            if(empty($mredis)){
                $dayInfos = config('acid.child_activity_ids');
                if (array_key_exists($this->nowTime, $dayInfos)) {
                    $aids = $dayInfos[$this->nowTime];
                } else {
                    $aids = reset($dayInfos);
                }
                //goods信息
                $mgoodsInfo = $this->mGoods->where('goods_id', 'in', $aids)
                    ->where('status',3)
                    ->where('state','<>',4)
                    ->field('goods_id,goods_name')->select();
                if (empty($mgoodsInfo)) {
                    return returnJosn([], 1);
                }
                $aGoodsInfo = $mgoodsInfo->toArray();
                // spec信息
                $oldmSpedInfo = $this->mSpec->where('goods_id', 'in', $aids)
                    ->where('is_putaway',1)
                    ->field('min(group_price) as group_price,shop_price,goods_id,stocknum as num')
                    ->group('goods_id')
                    ->select()->toArray();

                $mSpedInfo = $this->mSpec->where('goods_id', 'in', $aids)
                    ->where('is_putaway',1)
                    ->where('stocknum','>',0)
                    ->field('group_price,shop_price,goods_id,stocknum as num')
                    ->order('group_price','asc')
                    ->select()->toArray();
                $mSpedInfo=$this->array_unset_tt($mSpedInfo,'goods_id');
                //imgfavs
                $mImgfavs = $this->mImgfavs->where('goods_id', 'in', $aids)
                    ->where('is_cover', 1)
                    ->field('goods_id,img320_url,img640_url')->select()->toArray();
                foreach ($aGoodsInfo as $k => $v) {
                    $aGoodsInfo[$k]['num'] = 0;
                    foreach ($mSpedInfo as $sk => $sv) {
                        if ($v['goods_id'] == $sv['goods_id']) {
                            if(isset($sv['group_price'])&&!empty($sv['group_price'])){
                                $aGoodsInfo[$k]['group_price'] = $sv['group_price'];
                            }
                            if(isset($sv['shop_price'])&&!empty($sv['shop_price'])){
                                $aGoodsInfo[$k]['shop_price'] = $sv['shop_price'];
                            }
                            if(isset($sv['num'])&&!empty($sv['num'])){
                                $aGoodsInfo[$k]['num'] = $sv['num'];
                            }else{
                                $aGoodsInfo[$k]['num'] = 0;
                            }

                        }
                    }

                    foreach ($mImgfavs as $km => $vm) {
                        if ($v['goods_id'] == $vm['goods_id']) {
                            if(isset($vm['img320_url'])&&!empty($vm['img320_url'])){
                                $aGoodsInfo[$k]['img320_url'] = $vm['img320_url'];
                            }
                            if(isset($vm['img640_url'])&&!empty($vm['img640_url'])){
                                $aGoodsInfo[$k]['img640_url'] = $vm['img640_url'];
                            }

                        }
                    }
                    if(!isset($aGoodsInfo[$k]['group_price'])){
                        foreach ($oldmSpedInfo as $olk=>$olv){
                            if ($v['goods_id']==$olv['goods_id']){
                                $aGoodsInfo[$k]['group_price']=$olv['group_price'];
                                $aGoodsInfo[$k]['shop_price']=$olv['shop_price'];
                            }
                        }
                    }
                }
                $this->mRedis->setchildinfo($aGoodsInfo);
                return returnJosn($aGoodsInfo, 1);
            }else{
                return returnJosn(json_decode($mredis,true),1);
            }

        } catch (\Exception $exception) {
            return returnJosn([], '200000');
        }
    }


    //618 淘好礼商品信息
    public function gettaogoods()
    {
        try {
            $mredis = $this->mRedis->gettaogoodsinfo();
            if(empty($mredis)){
                $dayInfos = config('acid.tao_goods_ids');
                if (array_key_exists($this->nowTime, $dayInfos)) {
                    $aids = $dayInfos[$this->nowTime];
                } else {
                    $aids = reset($dayInfos);
                }
                //goods信息
                $mgoodsInfo = $this->mGoods->where('goods_id', 'in', $aids)
                    ->where('status',3)
                    ->where('state','<>',4)
                    ->field('goods_id,goods_name')->select();
                if (empty($mgoodsInfo)) {
                    return returnJosn([], 1);
                }
                $aGoodsInfo = $mgoodsInfo->toArray();
                // spec信息
                $oldmSpedInfo = $this->mSpec->where('goods_id', 'in', $aids)
                    ->where('is_putaway',1)
                    ->field('min(group_price) as group_price,shop_price,goods_id,stocknum as num')
                    ->group('goods_id')
                    ->select()->toArray();

                $mSpedInfo = $this->mSpec->where('goods_id', 'in', $aids)
                    ->where('is_putaway',1)
                    ->where('stocknum','>',0)
                    ->field('group_price,shop_price,goods_id,stocknum as num')
                    ->order('group_price','asc')
                    ->select()->toArray();
                $mSpedInfo=$this->array_unset_tt($mSpedInfo,'goods_id');
                //imgfavs
                $mImgfavs = $this->mImgfavs->where('goods_id', 'in', $aids)
                    ->where('is_cover', 1)
                    ->field('goods_id,img320_url,img640_url')->select()->toArray();
                foreach ($aGoodsInfo as $k => $v) {
                    $aGoodsInfo[$k]['num'] = 0;
                    foreach ($mSpedInfo as $sk => $sv) {
                        if ($v['goods_id'] == $sv['goods_id']) {
                            if(isset($sv['group_price'])&&!empty($sv['group_price'])){
                                $aGoodsInfo[$k]['group_price'] = $sv['group_price'];
                            }
                            if(isset($sv['shop_price'])&&!empty($sv['shop_price'])){
                                $aGoodsInfo[$k]['shop_price'] = $sv['shop_price'];
                            }
                            if(isset($sv['num'])&&!empty($sv['num'])){
                                $aGoodsInfo[$k]['num'] = $sv['num'];
                            }else{
                                $aGoodsInfo[$k]['num'] = 0;
                            }

                        }
                    }

                    foreach ($mImgfavs as $km => $vm) {
                        if ($v['goods_id'] == $vm['goods_id']) {
                            if(isset($vm['img320_url'])&&!empty($vm['img320_url'])){
                                $aGoodsInfo[$k]['img320_url'] = $vm['img320_url'];
                            }
                            if(isset($vm['img640_url'])&&!empty($vm['img640_url'])){
                                $aGoodsInfo[$k]['img640_url'] = $vm['img640_url'];
                            }

                        }
                    }
                    if(!isset($aGoodsInfo[$k]['group_price'])){
                        foreach ($oldmSpedInfo as $olk=>$olv){
                            if ($v['goods_id']==$olv['goods_id']){
                                $aGoodsInfo[$k]['group_price']=$olv['group_price'];
                                $aGoodsInfo[$k]['shop_price']=$olv['shop_price'];
                            }
                        }
                    }
                }
                $this->mRedis->settaogoodsinfo($aGoodsInfo);
                return returnJosn($aGoodsInfo, 1);
            }else{
                return returnJosn(json_decode($mredis,true),1);
            }

        } catch (\Exception $exception) {
//            $logArr=[
//                'apiType'=>3,
//                'url'=>SRequest::url(true),
//                'apiUrl'=>'',
//                'params'=>['a'=>1],
//                'result'=>-1,
//                'message'=>'请求失败',
//                'logLevel'=>3,
//                'clientIp'=>SRequest::ip(),
//                'logTime'=>time(),
//            ];
//            $url=config('bkapi.lo_api_url');
//            $resLog=httpPost($url,$logArr);
            return returnJosn([], '200000');
        }
    }
}