<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-03-13
 * Time: 16:30
 */
namespace app\zhuanzhuan\controller;

use think\App;
use think\Controller;

class Baseapi extends Controller {

    public function __construct(App $app = null)
    {
        parent::__construct($app);
        $filter = config('filter');
        $params=$app->request->param();
        $data=[];
        $param = urldecode(http_build_query($params));
        foreach ($filter as $k => $v) {
            if (strstr($param, $v)) {
                $data[]=$v;
            }
        }
        if(empty($data)) return true;
        echo 'check error';exit;
    }
}