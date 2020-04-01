<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-06-12
 * Time: 14:10
 */
namespace app\activity\controller;

use think\Controller;
use think\Request;
use app\activity\model\Activityredis;

class Earn extends Controller{

    public function returninfo($data,$msg,$result=1)
    {
      return json(
          [
              'data'=>$data,
              'message'=>$msg,
              'result'=>$result,
          ]
      );
    }

    //赚赚618活动 领取接口
    public function getinfo(Request $request)
    {
       $userId=$request->param('user_id');
       $type=$request->param('type',1);//1 判断是否领取  2 用户主动领取
       if(empty($userId)) return $this->returninfo([],'参数不能为空',1);
       $mReis=new Activityredis();
       if($type==1){
           $getUserTime=$mReis->getzzsix($userId);
           return $this->returninfo(['getTime'=>empty($getUserTime)?'':$getUserTime],'用户领取时间',1);
       }else{
           $mReis->setzzsix($userId);
           $finalTime=$mReis->getzzsix($userId);
           return $this->returninfo(['getTime'=>$finalTime],'领取成功',1);
       }
    }


}