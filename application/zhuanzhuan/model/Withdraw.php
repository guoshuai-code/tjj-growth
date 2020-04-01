<?php
/**
 * 赚赚支付宝提现业务
 * User: 李祎
 * Date: 2019/5/30
 * Time: 16:56
 */

namespace app\zhuanzhuan\model;

class Withdraw extends Common
{
    /**
     * 提现列表数据
     * @param $user_id
     * @return array
     */
    public function withdraw_list($user_id)
    {
        //新表数据
        $withdrawModel = $this->dataModel($this::WITHDRAW);
        $field_new = "user_id,user_account,amount,balance,status,from_unixtime(update_time) as create_time,error_message";
        $where_new = array(
            'user_id' => $user_id,
        );
        $new_data = $withdrawModel->field($field_new)->where($where_new)->order('id desc')->select();

        //旧表数据
        $financeModel = $this->dataModel($this::FINANCE);
        $field_old = array(
            "user_id" => "user_id",
            "user_account" => "user_account",
            "pay_money" => "amount",
            "status" => "status",
            "from_unixtime(act_time)" => "create_time",
            "error_msg" => "error_message",
        );
        $where_old = array(
            'user_id' => $user_id,
        );
        $old_data = $financeModel->field($field_old)->where($where_old)->order('create_time desc')->select();
        foreach ($old_data as $key => $val) {
            $old_data[$key]['balance'] = '';
            switch ($val['status']) {
                case 2:
                    $old_data[$key]['status'] = 3;
                    break;
                case 3:
                    $old_data[$key]['status'] = 3;
                    break;
                case 4:
                    $old_data[$key]['status'] = 2;
                    break;
            }
        }
        //数据整合
        $result = array_merge($new_data, $old_data);
        return $result;
    }

    /**
     * 用户账户信息
     * @param $user_id
     * @return mixed
     */
    public function account_info($user_id)
    {
        $accountModel = $this->dataModel($this::ACCOUNT);
        $field = "user_id,balance";
        $where = array(
            "user_id" => $user_id,
        );
        $result = $accountModel->field($field)->where($where)->find();
        return $result;
    }

    /**
     * 今日个人提现金额
     * @param $user_id
     * @return float
     */
    public function today_amount($user_id)
    {
        $withdrawModel = $this->dataModel($this::WITHDRAW);
        $where_new = array(
            'user_id' => $user_id,
            'create_time' => ['gt', strtotime(date("Y-m-d", time()))],
            'status' => ['lt', 3],
        );
        $result = $withdrawModel->where($where_new)->order('id desc')->sum('amount');
        return $result;
    }

    /**
     * 用户提现数据查询
     * @param $user_id
     * @param $id
     * @return array|null|\PDOStatement|string|\think\Model
     */
    public function user_withdraw($user_id, $id)
    {
        $withdrawModel = $this->dataModel($this::WITHDRAW);
        $where = array(
            'user_id' => $user_id,
            'id' => $id
        );
        $result = $withdrawModel->field('amount,create_time')->where($where)->find();
        return $result;
    }

    /**
     * 今日平台提现总额
     * @return float
     */
    public function total_amount()
    {
        $withdrawModel = $this->dataModel($this::WITHDRAW);
        $where_new = array(
            'create_time' => ['gt', strtotime(date("Y-m-d", time()))],
            'status' => ['lt', 3],
        );
        $result = $withdrawModel->where($where_new)->order('id desc')->sum('amount');
        return $result;
    }

    /**
     * 提现表数据写入
     * @param $data
     * @return mixed：添加数据的主键
     */
    public function withdraw_insert($data)
    {
        $withdrawModel = $this->dataModel($this::WITHDRAW);
        $result = $this->insert_data_one($withdrawModel, $data);
        return $result;
    }

    /**
     * 用户账户表余额数据更新
     * @param $user_id
     * @param $data
     * @return mixed：受影响的条数，无修改则返回0
     */
    public function account_update($user_id, $data)
    {
        $accountModel = $this->dataModel($this::ACCOUNT);
        $where = array(
            'user_id' => $user_id,
        );
        $result = $this->update_data(1, $accountModel, $where, $data);
        return $result;
    }

    /**
     * 提现结果数据更新
     * @param $id
     * @param $data
     * @return mixed：受影响的条数，无修改则返回0
     */
    public function withdraw_update($id, $user_id, $data)
    {
        $withdrawModel = $this->dataModel($this::WITHDRAW);
        $where = array(
            'id' => $id,
            'user_id' => $user_id,
            'status' => ['gt', 1]
        );
        $result = $this->update_data(1, $withdrawModel, $where, $data);
        return $result;
    }
}