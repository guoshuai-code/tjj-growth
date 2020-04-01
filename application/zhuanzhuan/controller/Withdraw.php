<?php
/**
 * 赚赚支付宝提现业务
 * User: 李祎
 * Date: 2019/5/30
 * Time: 15:28
 */

namespace app\zhuanzhuan\controller;

use think\App;
use think\cache\driver\Redis;
use think\facade\Cache;
use think\Db;
class Withdraw extends Common
{
    const MODEL_NAME = 'Withdraw';

    #########################redis属性设置##########################################
    const EXPIRATION = 300; //默认缓存时间5分钟
    const EXPIRATION_ONEHOUR = 3600; //缓存时间1小时
    const EXPIRATION_ONEDAY = 86400;//缓存1天时间
    const EXPIRATION_ONEWEEK = 604800;//缓存1周时间
    public $redis; //设置redis对象
    #########################redisKEY###############################################
    const KEY = "ZHUANZHUAN-WITHDRAW-";
    const FK_KEY = "zzblacklist_";//风控黑名单redis

    public function __construct(App $app = null)
    {
        parent::__construct($app);
        $request = $app->request->param();
        $this->filter($request);
        $this->redis = new Redis(config('redis'));
        $this->handler = $this->redis->handler();
    }

    /**
     * 提现列表
     * withdraw_list数据中的status：1，提现中；2，提现成功；3，提现失败。
     * @param int $page
     */
    public function withdraw_list($page = 1)
    {
        $request = $this->request->param();
        //用户校验
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;

        //获取列表数据
        $key_list = $this::KEY . "LIST-USER_ID:" . $request['user_id'];
        $redis_data = $this->redis->get($key_list);
        if (empty($redis_data)) {
            $list_info = model($this::MODEL_NAME)->withdraw_list($request['user_id']);
            $this->redis->set($key_list, $list_info, $this::EXPIRATION_ONEDAY);
        } else {
            $list_info = $redis_data;
        }

        //分页
        $result['data']['withdraw_list'] = array_slice($list_info, 40 * ($page - 1), 40);

        //获取用户账户信息数据
        $account_info = model($this::MODEL_NAME)->account_info($request['user_id']);
        $result['data']['balance'] = empty($account_info['balance']) ? 0 : $account_info['balance'];

        //获取用户当日已提现金额
        $result['data']['today_amount'] = $this->today_amount($request['user_id']);
        $result['result'] = 1;
        $this->interlayer($result);
    }

    /**
     * 用户提现操作
     * 用户单日提现金额为2~100，余额低于2不能提现，高于100只提100
     * @param $user_id
     */
    public function withdraw($user_id, $user_account, $user_name, $phone_info)
    {
        //风控黑名单接入
        $redisT = new Redis(config('blackRedis'));
        $handler = $redisT->handler();
        $blackArr = $handler->hgetall($this::FK_KEY . $user_id);
        if (!empty($blackArr) && $blackArr['state'] == 1) {
            //用户在黑名单中，禁止提现
            $result = [
                'result' => '-8',
                'data' => [],
            ];
            $this->interlayer($result);
        }

        $request = $this->request->param();
        //用户校验
        $user_check = $this->checkToken($user_id, $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;

        $total_amount = $this->total_amount();
        if ($total_amount >= 50000) {
            //单日平台提现超过5万元
            $result = [
                'result' => '-7',
                'data' => [],
            ];
            $this->interlayer($result);
        }

        //获取用户账户信息数据
        $account_info = model($this::MODEL_NAME)->account_info($user_id);
        if (empty($account_info['balance']) || $account_info['balance'] < 2) {
            //账户余额不足
            $result = [
                'result' => '-2',
                'data' => [],
            ];
            $this->interlayer($result);
        }
        $today_amount = $this->today_amount($user_id);
        if ($today_amount >= 100) {
            //单日提现超过一百
            $result = [
                'result' => '-3',
                'data' => [],
            ];
            $this->interlayer($result);
        }
        $amount = ($account_info['balance'] > (100 - $today_amount)) ? (100 - $today_amount) : $account_info['balance'];

        //开启事务，准备数据写入
        Db::startTrans();
        $insert_data = array(
            'user_id' => $user_id,
            'user_account' => $user_account,
            'user_name' => $user_name,
            'amount' => $amount,
            'balance' => $account_info['balance'] - $amount,
            'status' => 1,
            'create_time' => time(),
            'update_time' => time(),
            'ip' => getIp(),
            'phone_info' => $phone_info
        );
        //提现表数据写入
        $withdraw_data_add = model($this::MODEL_NAME)->withdraw_insert($insert_data);
        if (empty($withdraw_data_add)) {
            Db::rollback();
            //withdraw表写入失败
            $result = [
                'result' => '-4',
                'data' => [],
            ];
            $this->interlayer($result);
        }
        $update_data = array(
            'balance' => $account_info['balance'] - $amount,
        );
        //账户表余额数据更新
        $account_update = model($this::MODEL_NAME)->account_update($user_id, $update_data);
        if (empty($account_update)) {
            Db::rollback();
            //account表更新失败
            $result = [
                'result' => '-5',
                'data' => [],
            ];
            $this->interlayer($result);
        }
        $params = array(
            'id' => $withdraw_data_add,
            'user_id' => $user_id,
            'amount' => $amount,
            'user_account' => $user_account,
            'user_name' => $user_name,
        );
        $list_result = curl_list("/message/recive", "earnWithdrawSend", $params);
        if (empty($list_result)) {
            Db::rollback();
            //访问队列失败
            $result = [
                'result' => '-6',
                'data' => [],
            ];
            $this->interlayer($result);
        }
        Db::commit();

        //数据更新成功，把提现金额写入amount redis
        $key_amount = $this::KEY . "AMOUNT-" . date("Y-m-d", time()) . "-user_id:" . $user_id;
        $this->redis->set($key_amount, $amount + $today_amount, $this::EXPIRATION_ONEDAY);

        //把提现金额写入total_amount redis
        $key_total = $this::KEY . "TOTAL_AMOUNT-" . date("Y-m-d", time());
        $this->redis->set($key_total, $amount + $total_amount, $this::EXPIRATION_ONEDAY);

        //删除提现列表缓存，保证数据及时展示
        $key_list = $this::KEY . "LIST-USER_ID:" . $user_id;
        $this->redis->rm($key_list);

        $result = [
            'result' => 1,
            'data' => [],
        ];
        $this->interlayer($result);
    }

    /**
     * 平台当日提现总额
     * @return int
     */
    public function total_amount()
    {
        $key = $this::KEY . "TOTAL_AMOUNT-" . date("Y-m-d", time());
        $amount = $this->redis->get($key);
        if (empty($amount)) {
            $amount = model($this::MODEL_NAME)->total_amount();
        }
        $total_amount = empty($amount) ? 0 : $amount;
        return $total_amount;
    }

    /**
     * 当日已提现金额查询
     * @param $user_id
     * @return int
     */
    public function today_amount($user_id)
    {
        $key_amount = $this::KEY . "AMOUNT-" . date("Y-m-d", time()) . "-user_id:" . $user_id;
        $amount = $this->redis->get($key_amount);
        if (empty($amount)) {
            $amount = model($this::MODEL_NAME)->today_amount($user_id);
        }
        $today_amount = empty($amount) ? 0 : $amount;
        return $today_amount;
    }

    /**
     * 提现结果处理
     */
    public function withdrawResult()
    {
        $body = file_get_contents('php://input');
        $request = json_decode($body, true);
        if (empty($request['user_id']) || empty($request['status']) || empty($request['id'])) {
            echo "参数不足！";
            die;
        }

        //开启事务，准备更新withdraw表
        Db::startTrans();
        $data = array(
            'status' => $request['status'],
            'update_time' => time(),
            'error_message' => empty($request['error_message']) ? '' : $request['error_message'],
        );
        $result = model($this::MODEL_NAME)->withdraw_update($request['id'], $request['user_id'], $data);
        if (empty($result)) {
            Db::rollback();
            echo "withdraw表更新失败";
            die;
        }

        //删除提现列表缓存，保证数据及时展示
        $key_list = $this::KEY . "LIST-USER_ID:" . $request['user_id'];
        $this->redis->rm($key_list);

        if ($request['status'] == 2) {
            Db::commit();
            echo "OK";
            die;
        }

        //查询用户提现数据
        $withdraw_info = model($this::MODEL_NAME)->user_withdraw($request['user_id'], $request['id']);

        //提现失败，用户account表余额数据数据更新，补回余额
        $account_data = array(
            'balance' => Db::raw("balance+" . $withdraw_info['amount']),
        );
        $acount_update = model($this::MODEL_NAME)->account_update($request['user_id'], $account_data);
        if (empty($acount_update)) {
            Db::rollback();
            echo "account表更新失败";
            die;
        }

        //判断此次提现是否为今日发起
        if ($withdraw_info['create_time'] >= strtotime(date("Y-m-d", time()))) {
            $total_amount = $this->total_amount();
            if (empty($total_amount)) {
                //当日平台尚无提现发生，无需更新缓存
                Db::commit();
                echo "OK";
                die;
            }

            //更新平台当日提现金额，释放此次失败的金额
            $total_amount = ($total_amount >= $withdraw_info['amount']) ? $total_amount - $withdraw_info['amount'] : 0;
            $key = $this::KEY . "TOTAL_AMOUNT-" . date("Y-m-d", time());
            $redis_result = $this->redis->set($key, $total_amount, $this::EXPIRATION_ONEDAY);
            if (empty($redis_result)) {
                Db::rollback();
                echo "total_amount redis写入失败";
                die;
            }

            $amount = $this->today_amount($request['user_id']);
            if (empty($amount)) {
                //今日未提现过，无需更新缓存
                Db::commit();
                echo "OK";
                die;
            }

            //更新今日提现金额记录，将此次失败提现金额返还
            $amount = ($amount >= $withdraw_info['amount']) ? $amount - $withdraw_info['amount'] : 0;
            $key_amount = $this::KEY . "AMOUNT-" . date("Y-m-d", time()) . "-user_id:" . $request['user_id'];
            $redis_result = $this->redis->set($key_amount, $amount, $this::EXPIRATION_ONEDAY);
            if (empty($redis_result)) {
                Db::rollback();
                echo "amount redis写入失败";
                die;
            } else {
                Db::commit();
                echo "OK";
                die;
            }
        } else {
            Db::commit();
            echo "OK";
            die;
        }
    }

    public function redis_get($key)
    {
        header('content-type:text/html;charset=utf-8');
        $redis_data = $this->redis->get($key);
        var_dump($redis_data);
        die;
    }
}