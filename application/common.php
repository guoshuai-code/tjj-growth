<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
//错误码
function errorCode($code=1)
{
    $errorCode=[
        '-1'=>'系统异常',
        '0'=>'',
        '1'=>'请求成功',
        '100000'=>'xxx参数错误',
        '100001'=>'用户id不能为空',
        '100002'=>'该用户不是黑名单',
        '100003'=>'用户是黑名单',
        '100004'=>'该用户今日已经显示过弹框了',
        '200000'=>'系统发生异常，请稍后再试',
        '100005'=>'该用户已经正式玩过游戏',
        '100006'=>'该用户已经还没有正式玩过游戏',
        '100007'=>'参数不全',
        '100008'=>'该用户已经开过团',
        '100009'=>'团id不能为空',
        '100010'=>'用户没有被邀请注册',
        '100011'=>'分享者没有开团',
        '100012'=>'不满足签到条件',
        '100013'=>'用户没有玩游戏次数',
        '100014'=>'用户截止到下单,在今天没有正式玩游戏',
        '100015'=>'没有查到团人数大于等于3的团id',
        '100016'=>'昨天生成的团，都是无效团',
        '100017'=>'不满足浏览条件',
        '100018'=>'被分享者玩游戏，分享者每天只能增加一次机会',
        '100019'=>'该用户不能抽奖',
        '100020'=>'tab的名称或者排序值已经存在',
        '100021'=>'今日奖品已经抽完',
        '100022'=>'用户身份信息验证失败',
        '100023'=>'已领取过优惠券',
        '100024'=>'用户抽奖失败',
        '-2001'=>'优惠券已领完',
        '-2002'=>'优惠券不存在',
        '-2003'=>'该优惠券已领取',
        '-2004'=>'领取优惠券失败',

    ];

    return $errorCode[$code];
}

function subCode($code=200)
{
    $errorCode=[
        '200'=>'200',
        '404'=>'404',
        '500'=>'500',
    ];

    return $errorCode[$code];
}

function realCode($code=0)
{
    $errorCode=[
        '0'=>'',
        '1'=>'参数为空',
        '2'=>'参数错误',
        '3'=>'参数一次',
    ];

    return $errorCode[$code];
}

function returnJosn($data=array(),$errorcode=0)
{
    $result=[
        'data'=>$data,
        'result'=>$errorcode,
        'message'=>errorCode($errorcode),
        'serverTime'=>time(),
    ];
    return json_encode($result);
}

function returnTjjJson($data=array(),$errorcode=0,$subcode=200,$realcode=0)
{
    $result=[
        'data'=>$data,
        'result'=>$errorcode,
        'message'=>errorCode($errorcode),
        'subCode'=>subCode($subcode),
        'realMessage'=>realCode($realcode),
    ];
    return json_encode($result);
}

//curl get请求
function httpGet($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}


//curl post请求
function httpPost($url, $param = array(),$type = 'application/json')
{
    $httph = curl_init($url);
    switch ($type){
        case 'application/json':
            $data = json_encode($param);
            curl_setopt($httph, CURLOPT_HTTPHEADER, array('Content-Type: '.$type, 'Content-Length: ' . strlen($data)));
            break;
        default:
            $data = http_build_query($param);
    }
    curl_setopt($httph, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($httph, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($httph, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($httph, CURLOPT_POST, 1);
    curl_setopt($httph, CURLOPT_POSTFIELDS, $data);
    curl_setopt($httph, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($httph, CURLOPT_CONNECTTIMEOUT , 10);
    curl_setopt($httph, CURLOPT_TIMEOUT, 10);
    $rst = curl_exec($httph);
    curl_close($httph);
    return $rst;
}


/**
 * @param $arr [要排序的数组]
 * @param $condition [要排序的条件, for  array('id'=>SORT_DESC,'add_time'=>SORT_ASC)]
 * @return bool|mixed
 * 对二维数组多个字段排序
 */
function SortArrByManyField($arr,$condition)
{
    if (empty($condition)) {
        return false;
    }
    $temp = array();
    $i = 0;
    foreach ($condition as $key => $ar) {
        foreach ($arr as $k => $a) {
            $temp[$i][] = $a[$key];
        }
        $i += 2;
        $temp[] = $arr;
    }
    $temp =& $arr;
    call_user_func_array('array_multisort', $temp);
    return array_pop($temp);
}

/**
 * 请求java队列接口
 * @param $business
 * @param $message
 * @return bool
 */
function curl_list($method , $business, $message)
{
    if (!$business || !is_string($business)) {
        //入参topic必须为字符串
        return false;
    }

    if (!is_array($message)) {
        //入参data必须为数组
        return false;
    }

    $message = json_encode($message);

    $url = 'http://' . config('DOMAIN_RABBITMQ') . $method;

    $data = [
        'business' => $business,
        'message' => $message,
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

/**
 * 获取java接口数据
 * @param $method
 * @param null $params
 * @param bool $json
 * @param null $host
 * @return mixed
 */
function java_api($method, $params = null, $json = false,$host = null){
    $post = isset($params['is_post']) ? $params['is_post'] : 0;
    $data['ip'] = getIp();
    $data = $post == 1 ? $data : array_merge((array) $data, (array) $params);
    $query = http_build_query($data);
    $host = empty($host) ? config("DOMAIN_API_TJJ_JAVA") : $host;
    $url = 'http://' . $host . '/' . $method.'?'. $query;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if ($post){
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($params));
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($ch);
    if (!$json) {
        $result = json_decode($result, TRUE);
    }
    $result['curl_errno'] = curl_errno($ch);
    //$result['url'] = $url;
    curl_close($ch);
    return $result;
}

/**
 * 获取用户公网IP
 * @return mixed
 */
function getIp()
{
    if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
        $ip = $_SERVER["HTTP_CLIENT_IP"];
    } else if (!empty($_SERVER["HTTP_X_REAL_FORWARDED_FOR"])) {
        $ip = $_SERVER["HTTP_X_REAL_FORWARDED_FOR"];
    } else if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
        $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
    } else if (!empty($_SERVER["REMOTE_ADDR"])) {
        $ip = $_SERVER["REMOTE_ADDR"];
    } else {
        $ip = '';
    }
    $ip = explode(',', $ip);
    return $ip[0];
}
