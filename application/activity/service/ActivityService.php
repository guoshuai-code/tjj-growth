<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-05-24
 * Time: 14:18
 */

namespace app\activity\service;

use think\Controller;
use app\activity\model\ActivityDetail;
use app\activity\model\ActivityTab;
use app\activity\model\Goods;
use app\activity\model\Imgfavs;
use app\activity\model\Spec;
use app\activity\model\Activityredis;
use think\facade\Log;
use app\activity\model\ActivityTaoGood;

class ActivityService extends Controller
{

    protected $activityId = 1;//618活动

    //查询 tab信息
    public function mGetTab($activityId, $tabId = 0)
    {
        try {
            $mTab = new ActivityTab();
            $where['activity_id'] = $activityId;
            if (!empty($tabId)) {
                $where['id'] = $tabId;
            }
            $res = $mTab::where($where)
                ->order('tab_sort', 'asc')
                ->select()->toArray();
            return ['data' => $res];
        } catch (\Exception $exception) {
            Log::info('查询tab表信息出错: ' . date('Y-m-d H:i:s'));
            return false;
        }
    }

    //获取tab列信息
    public function getTabInfo()
    {
        $mTab = new ActivityTab();
        $res = $mTab::column('tab_name');
        return $res;
    }

    //查询tab sort是否存在
    public function havaTabInfo($sort, $tabName)
    {
        try {
            $mTab = new ActivityTab();
            $res = $mTab::where('tab_sort', $sort)
                ->whereOr('tab_name', $tabName)
                ->field('tab_name,tab_sort')
                ->findOrEmpty()->toArray();
            return $res;
        } catch (\Exception $exception) {
            Log::info('查询tab表信息出错: ' . date('Y-m-d H:i:s'));
            return false;
        }
    }

    //添加 tab 信息
    public function mAddTab($arr = array())
    {
        try {
            $mTab = new ActivityTab();
            $res = $mTab->insert($arr);
            return $res;
        } catch (\Exception $exception) {
            Log::info('添加tab表信息出错：' . date('Y-m-d H:i:s'));
            return false;
        }
    }


    public function getIdTabInfo($id,$name,$sort)
    {
        try {
            $mTab = new ActivityTab();
            $res = $mTab::where('id', '<>', $id)
                ->field('tab_sort,tab_name')
                ->select()->toArray();
            $names=array_flip(array_column($res,'tab_name'));
            $sorts=array_flip(array_column($res,'tab_sort'));
            $havaName='';
            $havaSort='';
            if(array_key_exists($name,$names)){
                $havaName=1;
            }
            if(array_key_exists($sort,$sorts)){
                $havaSort=1;
            }
            return ['havaname'=>$havaName,'havasort'=>$havaSort];
        } catch (\Exception $exception) {
            Log::info('查询tab表信息出错: ' . date('Y-m-d H:i:s'));
            return false;
        }
    }

    //编辑 tab 信息
    public function mEditTab($id, $arr = array())
    {
        try {
            $mTab = new ActivityTab();
            $info = $mTab::where('id', $id)->findOrEmpty()->toArray();
            if (empty($info)) return false;
            $res = $mTab::where('id', $id)->update($arr);
            return $res;
        } catch (\Exception $exception) {
            Log::info('编辑tab表信息出错：' . date('Y-m-d H:i:s'));
            return false;
        }
    }

    //删除 tab 信息
    public function mDelTab($id)
    {
        try {
            $mTab = new ActivityTab();
            $info = $mTab::where('id', $id)->findOrEmpty()->toArray();
            if (empty($info)) return false;
            $res = $mTab::where('id', $id)->delete();
            return $res;
        } catch (\Exception $exception) {
            Log::info('删除tab表信息出错：' . date('Y-m-d H:i:s'));
            return false;
        }

    }

    //查询 根据tabId 查询detail信息---
    public function mGetDetail($arr, $start, $size, $id = 0)
    {
        try {
            $mDetail = new ActivityDetail();
            $where = [];
            $where['activity_id'] = $this->activityId;//618活动
            if (!empty($arr['goods_id'])) {
                $where['goods_id'] = $arr['goods_id'];
            }
            if (!empty($arr['coupon_id'])) {
                $where['coupon_id'] = $arr['coupon_id'];
            }
            if (!empty($id)) {
                $where['id'] = $id;
            }
            $res = $mDetail::where($where)
                ->order(['goods_sort' => 'asc', 'tab_id' => 'asc'])
                ->limit($start, $size)
                ->select()->toArray();
            $count = $mDetail::where($where)->count();
            return ['count' => $count, 'data' => $res];
        } catch (\Exception $exception) {
            Log::info('查询detail表信息出错：' . date('Y-m-d H:i:s'));
            return false;
        }
    }

    //编辑 detail信息 $detailId 主键ID
    public function mEditDetail($detailId, $data = array())
    {
        try {
            $mDetail = new ActivityDetail();
            $info = $mDetail::where('id', $detailId)
                ->field('id')->findOrEmpty()->toArray();
            if (empty($info)) return false;
            $res = $mDetail::where('id', $detailId)->update($data);
            return $res;
        } catch (\Exception $exception) {
            Log::info('编辑detail表信息出错：' . date('Y-m-d H:i:s'));
            return false;
        }
    }

    //添加 detail 信息
    public function mAddDetail($array = array())
    {
        try {
            $mDetail = new ActivityDetail();
            $data = [];
            $arr = [
                'activity_id' => $this->activityId,
            ];
            foreach ($array as $k => $v) {
                $data[] = array_merge($arr, $v);
            }
            $res = $mDetail->insertAll($data);
            return $res;
        } catch (\Exception $exception) {
            Log::info('添加detail表信息出错：' . date('Y-m-d H:i:s'));
            return false;
        }
    }

    //删除 detail 信息 $detailId 主键ID
    public function mDelDetail($detailId)
    {
        try {
            $mDetail = new ActivityDetail();
            $info = $mDetail::where('id', $detailId)->findOrEmpty()->toArray();
            if (empty($info)) return false;
            $res = $mDetail::where('id', $detailId)->delete();
            return $res;
        } catch (\Exception $exception) {
            Log::info('删除detail表信息出错：' . date('Y-m-d H:i:s'));
            return false;
        }
    }

    public function mDayGoods($tabName, $start, $size)
    {
        try {
            $mDetail = new ActivityDetail();
            $where['tab_name'] = $tabName;
            $res = $mDetail::where('tab_name', $tabName)
                ->whereTime('start_time', '<=', time())
                ->whereTime('end_time', '>=', time())
                ->where('activity_id', $this->activityId)
                ->field('id,tab_name,goods_id,coupon_id,start_time,end_time,goods_sort')
                ->limit($start, $size)
                ->select()->toArray();
            $count = $mDetail::where('tab_name', $tabName)
                ->whereTime('start_time', '<=', time())
                ->whereTime('end_time', '>=', time())
                ->where('activity_id', $this->activityId)
                ->count();
            return ['data' => $res, 'count' => $count];
        } catch (\Exception $exception) {
            Log::info('前端接口,返回tab详情页异常');
            return false;
        }

    }

    public function getgoodsInfo($aids)
    {
        $mGoods = new Goods();
        $mSpec = new Spec();
        $mImgfavs = new Imgfavs();
        //goods信息
        $mgoodsInfo = $mGoods->where('goods_id', 'in', $aids)
            ->where('status', 3)
            ->where('state', '<>', 4)
            ->field('goods_id,goods_name')->select();
        if (empty($mgoodsInfo)) {
            return returnJosn([], 1);
        }
        $aGoodsInfo = $mgoodsInfo->toArray();
        // spec信息
        $oldmSpedInfo = $mSpec->where('goods_id', 'in', $aids)
            ->where('is_putaway', 1)
            ->field('min(group_price) as group_price,shop_price,goods_id,stocknum as num')
            ->group('goods_id')
            ->select()->toArray();


        $mSpedInfo = $mSpec->where('goods_id', 'in', $aids)
            ->where('is_putaway', 1)
            ->where('stocknum', '>', 0)
            ->field('group_price,shop_price,goods_id,stocknum as num')
            ->order('group_price', 'asc')
            ->select()->toArray();
        $mSpedInfo = $this->array_unset_tt($mSpedInfo, 'goods_id');

        //imgfavs
        $mImgfavs = $mImgfavs->where('goods_id', 'in', $aids)
            ->where('is_cover', 1)
            ->field('goods_id,img320_url,img640_url')->select()->toArray();
        foreach ($aGoodsInfo as $k => $v) {
            $aGoodsInfo[$k]['num'] = 0;
            foreach ($mSpedInfo as $sk => $sv) {
                if ($v['goods_id'] == $sv['goods_id']) {
                    if (isset($sv['group_price']) && !empty($sv['group_price'])) {
                        $aGoodsInfo[$k]['group_price'] = $sv['group_price'];
                    }
                    if (isset($sv['shop_price']) && !empty($sv['shop_price'])) {
                        $aGoodsInfo[$k]['shop_price'] = $sv['shop_price'];
                    }
                    if (isset($sv['num']) && !empty($sv['num'])) {
                        $aGoodsInfo[$k]['num'] = $sv['num'];
                    } else {
                        $aGoodsInfo[$k]['num'] = 0;
                    }

                }
            }

            foreach ($mImgfavs as $km => $vm) {
                if ($v['goods_id'] == $vm['goods_id']) {
                    if (isset($vm['img320_url']) && !empty($vm['img320_url'])) {
                        $aGoodsInfo[$k]['img320_url'] = $vm['img320_url'];
                    }
                    if (isset($vm['img640_url']) && !empty($vm['img640_url'])) {
                        $aGoodsInfo[$k]['img640_url'] = $vm['img640_url'];
                    }

                }
            }

            if (!isset($aGoodsInfo[$k]['group_price'])) {
                foreach ($oldmSpedInfo as $olk => $olv) {
                    if ($v['goods_id'] == $olv['goods_id']) {
                        $aGoodsInfo[$k]['group_price'] = $olv['group_price'];
                        $aGoodsInfo[$k]['shop_price'] = $olv['shop_price'];
                    }
                }
            }

        }
        return $aGoodsInfo;
    }

    protected function array_unset_tt($arr, $key = 'goods_id')
    {
        //建立一个目标数组
        $res = array();
        foreach ($arr as $value) {
            //查看有没有重复项
            if (isset($res[$value[$key]])) {
                unset($value[$key]);  //有：销毁
            } else {
                $res[$value[$key]] = $value;
            }
        }
        return $res;
    }


    //添加吉祥物信息
    public function mAddmascot($userId, $filedName)
    {
        try {
            $startTime="2019-06-11 00:00:00";
            $uStrat=strtotime($startTime);
            $mascot = new ActivityTaoGood();
            $info = $mascot::where('user_id', $userId)
                ->where('add_time','>=',$uStrat)
                ->findOrEmpty()->toArray();
            if (!empty($info)) {
                $res = $mascot::where('user_id', $userId)
                    ->where('add_time','>=',$uStrat)
                    ->setInc($filedName);
            } else {
                $arr = [
                    'user_id' => $userId,
                    $filedName => 1,
                    'add_time' => time(),
                ];
                $res = $mascot->insert($arr);
            }
            return $res;
        } catch (\Exception $exception) {
            Log::info('添加 tao_good 表信息出错：' . date('Y-m-d H:i:s'));
            return false;
        }
    }

    //用户吉祥物次数
    public function mGetCount($userId)
    {
        try {
            $mascot = new ActivityTaoGood();
            $startTime="2019-06-11 00:00:00";
            $uStrat=strtotime($startTime);
            $res = $mascot::where('user_id', $userId)
                ->where('add_time','>=',$uStrat)
                ->field('t_xiaodi,t_xiake,t_boshi,t_dafu,t_baobao')
                ->findOrEmpty()->toArray();
            return $res;
        } catch (\Exception $exception) {
            Log::info('查询 tao_good 表信息出错：' . date('Y-m-d H:i:s'));
            return false;
        }
    }

    //用户是否可以合成淘老板
    public function drawInfo($userId)
    {
        try {
            $where = [
                ['user_id', '=', $userId],
                ['t_xiaodi', '>=', 1],
                ['t_xiake', '>=', 1],
                ['t_boshi', '>=', 1],
                ['t_dafu', '>=', 1],
                ['t_baobao', '>=', 1],
            ];
            $startTime="2019-06-11 00:00:00";
            $uStrat=strtotime($startTime);
            $mascot = new ActivityTaoGood();
            $res = $mascot::where($where)
                ->where('add_time','>=',$uStrat)
                ->field('user_id,t_xiaodi,t_xiake,t_boshi,t_dafu,t_baobao')
                ->findOrEmpty()->toArray();
            return $res;
        } catch (\Exception $exception) {
            Log::info('查询 tao_good 表信息出错：' . date('Y-m-d H:i:s'));
            return false;
        }
    }

    //用户合成淘老板 减少用户抽奖信息
    public function decUserInfo($userId)
    {
        try {
            $startTime="2019-06-11 00:00:00";
            $uStrat=strtotime($startTime);
            $mascot = new ActivityTaoGood();
            $res = $mascot::where('user_id', $userId)
                ->where('add_time','>=',$uStrat)
                ->dec('t_xiaodi', 1)
                ->dec('t_xiake', 1)
                ->dec('t_boshi', 1)
                ->dec('t_dafu', 1)
                ->dec('t_baobao', 1)->update();
            return $res;
        } catch (\Exception $exception) {
            return false;
        }
    }
}