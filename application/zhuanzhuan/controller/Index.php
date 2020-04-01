<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-03-13
 * Time: 16:33
 */
namespace app\zhuanzhuan\controller;

use think\Controller;


class Index extends Controller
{


    public function index()
    {
      return $this->fetch('index');
    }
}