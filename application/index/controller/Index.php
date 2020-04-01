<?php

namespace app\index\controller;

use think\facade\Log;
use think\facade\Request;

class Index
{
    public function index()
    {
        return 'tjj-zhuanzhuan';
//        return '<style type="text/css">*{ padding: 0; margin: 0; } div{ padding: 4px 48px;} a{color:#2E5CD5;cursor: pointer;text-decoration: none} a:hover{text-decoration:underline; } body{ background: #fff; font-family: "Century Gothic","Microsoft yahei"; color: #333;font-size:18px;} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.6em; font-size: 42px }</style><div style="padding: 24px 48px;"> <h1>:) </h1><p> ThinkPHP V5.1<br/><span style="font-size:30px">12载初心不改（2006-2018） - 你值得信赖的PHP框架</span></p></div><script type="text/javascript" src="https://tajs.qq.com/stats?sId=64890268" charset="UTF-8"></script><script type="text/javascript" src="https://e.topthink.com/Public/static/client.js"></script><think id="eab4b9f840753f8e7"></think>';
    }


    public function test()
    {
        $a=date("Y-m-d H:i:s",strtotime('+3 day'));
        $b=strtotime('+2 days');
        var_dump($b);
        var_dump($a);exit;
    }

    public function proxyRequest(\think\Request $request)
    {
        $param = $request->param();
        $method = empty($param['method'])?'':$param['method'];
        $selectUrl=empty($param['select_url'])?'':$param['select_url'];

        if(empty($selectUrl)){
            $url=config('proxy')[4];
        }else{
            $url=config('proxy')[$selectUrl];
        }

        if (strtolower($method)== 'post') {
            $aParam=json_encode($param);
            $sparam=http_build_query($aParam);
            $info=httpPost($url,$sparam);
        } else {
            $pathinfo=$request->url();
            $surl=substr($pathinfo,strpos($pathinfo,'?')+1);
            $new=$url.'?'.$surl;
            Log::info('时间为:'.time().'赚赚-请求地址为:'.$new);
            $info=httpGet($new);
            Log::info('赚赚-请求地址为:'.$new.'赚赚转发返回时间'.time());
        }
       return $info;
    }

    //赚赚项目转发
    public function zzproxy(\think\Request $request)
    {
        $host=config('proxy')['1'].'/api.php?';
        $param=$request->param();
        unset($param['api_url']);
        $sparam=http_build_query($param);
        $apiUrl=$request->param('api_url');
        $mapiUrl=explode('-',$apiUrl);
        $g=$mapiUrl[0];
        $m=$mapiUrl[1];
        $a=$mapiUrl[2];
        $url=$host.'&g='.$g.'&m='.$m.'&a='.$a.'&'.$sparam;
        $info=httpGet($url);
        return $info;

    }

}
