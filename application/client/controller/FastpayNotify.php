<?php

namespace app\client\controller;

use app\model\BankDB;
use app\model\GameOC;
use app\model\MasterDB;
use app\model\UserDB;
use log\log;
use ssspay\PaySdk;
use redis\Redis;
use socket\sendQuery;
use think\Controller;
use think\Exception;
use think\facade\Cache;
use ssspay\Utility;

class FastpayNotify extends Controller
{

    public function notify()
    {
        //{"amount":"100.00","ext":"63018399","finishPayTime":"20220916175404","merchantNo":"1570679338098274306","msg":"success","orderNo":"TP200202209161752314589","platformOrderNo":"1570749973840867329","replacementOrderNo":"","sign":"A90D67BFF2406D87590A639BBE780AA8","startTime":"20220916175232","status":1,"version":"2.0.1"}
        try {
            $json = file_get_contents('php://input');
            save_log('fastpay', '代收回调通知:' . $json);
            $params = json_decode($json, true);
            if (empty($params)) {
                exit('Empty Request');
            }
            $orderid = $params['orderNo'];
            $realmoney = $params['amount'];
            $transactionId = $params['platformOrderNo'];
            $sign = $params['sign'];
            unset($params['sign']);
            if (empty($orderid) || empty($realmoney) || empty($transactionId)) {
                exit('Empty Request');
            }
            $db = new UserDB();
            $order = $db->getTableRow('T_UserChannelPayOrder', ['OrderId' => $orderid], '*');
            if (empty($order)) {
                $gameoc = new GameOC();
                $data = [
                    'OrderId' => $orderid,
                    'Controller' => 'Notify',
                    'Method' => __METHOD__,
                    'Parameter' => $json,
                    'Error' => '订单不存在',
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $gameoc->PaynotifyLog()->insert($data);
                exit('success');
            }

            $ChannelId = $order['ChannelID'];
            $masterA = new MasterDB();
            $channel = $masterA->getTableRow('T_GamePayChannel', ['channelID' => $ChannelId], '*');
            $detail = json_decode($channel['MerchantDetail'], true);
            $checksign = $this->createSign($params, $detail['secret']);
            $text = 'success';
            if ($checksign == $sign) { //验签 Verify signature
                //订单状态，0：待支付，1：支付成功 Order status, 0: Unpaid, 1: paid
                $status = $params['status'];
                $rdskey = 'outnotify' . $orderid . $status;
                if (!$this->set_mutex($rdskey)) {
                    Exit('SUCCESS');
                }
                $log_txt = '';
                if ($status == '1') {
                    $userdb = new UserDB();
                    $order = $userdb->getTableRow('T_UserChannelPayOrder', ['OrderId' => $orderid], '*');
                    if ($order['Status'] == 1) {
                        $log_txt = '订单已经支付';
                        $gameoc = new GameOC();
                        $data = [
                            'OrderId' => $orderid,
                            'Controller' => 'Notify',
                            'Method' => __METHOD__,
                            'Parameter' => $json,
                            'Error' => $log_txt,
                            'AddTime' => date('Y-m-d H:i:s', time())
                        ];
                        $gameoc->PaynotifyLog()->insert($data);
                        exit($text);
                    }

                    $master = new MasterDB();
                    $itemM = $master->getTableRow('T_ShopItem', 'RealMoney=' . $realmoney, '*');
                    $Money = $itemM['BaseGoodsValue']; //实际金币 对应表 masterdb.t_shopitem BaseGoodsValue
                    $sendQuery = new  sendQuery();
                    $res = $sendQuery->callback('CMD_MD_USER_CHANNEL_RECHARGE', [$order['AccountID'], $ChannelId, $transactionId, $detail['currency'], $realmoney, $Money, $order['PayType']]);
                    $code = unpack("LCode", $res)['Code'];
                    save_log('fastpay', '玩家id:' . $order['AccountID'] . ',订单id:' . $transactionId . ',金币发送状态' . $code);
                    if (intval($code) === 0) {
                        $data = [
                            'PayTime' => date('Y-m-d H:i:s', time()),
                            'Status' => 1,
                            'TransactionNo' => $transactionId
                        ];
                        $user = new UserDB();
                        $ret = $user->updateTable('T_UserChannelPayOrder', $data, ['OrderId' => $orderid]);
                        $log_txt = '订单充值成功';
                    } else {//上分失败
                        $data = [
                            'PayTime' => date('Y-m-d H:i:s', time()),
                            'Status' => 2,
                            'TransactionNo' => $transactionId
                        ];
                        $user = new UserDB();
                        $ret = $user->updateTable('T_UserChannelPayOrder', $data, ['OrderId' => $orderid]);
                        $log_txt = '金币未发放成功';
                        $text = 'success';
                    }

                } else if ($status == '2') {
                    exit('success');
                } else {
                    $data = [
                        'PayTime' => date('Y-m-d H:i:s', time()),
                        'Status' => 3,
                        'TransactionNo' => $transactionId
                    ];
                    $user = new UserDB();
                    $ret = $user->updateTable('T_UserChannelPayOrder', $data, ['OrderId' => $orderid]);
                    $log_txt = '第三方订单处理失败' . $params['msg'];
                    $text = "OK"; //处理失败 Processing failed
                }
                $gameoc = new GameOC();
                $data = [
                    'OrderId' => $orderid,
                    'Controller' => 'Notify',
                    'Method' => __METHOD__,
                    'Parameter' => $json,
                    'Error' => $log_txt,
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $gameoc->PaynotifyLog()->insert($data);
            } else {
                $text = "SIGN_ERROR"; //签名失败 Signature failed
                $gameoc = new GameOC();
                $data = [
                    'OrderId' => $orderid,
                    'Controller' => 'Notify',
                    'Method' => __METHOD__,
                    'Parameter' => $json,
                    'Error' => $text,
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $gameoc->PaynotifyLog()->insert($data);
            }
            exit('success');
        } catch (Exception $ex) {
            save_log('fastpay', 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            exit('success');
        }
    }


    public function outnotify()
    {
        try {
            $json = file_get_contents('php://input');
            save_log('fastpay', '代付回调通知:' . $json);
            $params = json_decode($json, true);
            if (empty($params)) {
                exit('Empty Request');
            }
            $sign = $params['sign'];
            // 验证签名参数是否正确
            unset($params['sign']);
            $orderid = $params['orderNo'];
            //$amount = $params['realAmount'];
            $status = $params['status'];

            if (empty($orderid) || empty($sign) || empty($status)) {
                exit('Empty Request');
            }
            $bankM = new BankDB();
            $order = $bankM->getTableRow('UserDrawBack', ['OrderNo' => $orderid], 'AccountID,ChannelId,iMoney,status');
            if (empty($order)) {
                $gameoc = new GameOC();
                $data = [
                    'OrderId' => $orderid,
                    'Controller' => 'Notify',
                    'Method' => __METHOD__,
                    'Parameter' => $json,
                    'Error' => '订单不存在',
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $gameoc->PaynotifyLog()->insert($data);
                exit('success');
            }

            $masterA = new  MasterDB();
            $channel = $masterA->getTableRow('T_GamePayChannel', ['channelID' => $order['ChannelId']], '*');
            $channelcfg = json_decode($channel['MerchantDetail'], true);
            $mysign = $this->createSign($params, $channelcfg['secret']); //生成签名 Generate signature
            if ($sign == $mysign) { //验签 Verify signature

                $rdskey = 'outnotify'.$orderid.$status;
                if(!$this->set_mutex($rdskey)){
                    Exit('SUCCESS');
                }

                $order_status = 0;
                $log_txt = '';
                $transactionId = $params['platformOrderNo'];
                $order_coin = intval($order['iMoney']);
                if ($order['status'] != 4) {
                    $log_txt = '订单状态不正确，非订单已经提交状态';
                    $gameoc = new GameOC();
                    $data = [
                        'OrderId' => $orderid,
                        'Controller' => 'Notify',
                        'Method' => __METHOD__,
                        'Parameter' => $json,
                        'Error' => $log_txt,
                        'AddTime' => date('Y-m-d H:i:s', time())
                    ];
                    $gameoc->PaynotifyLog()->insert($data);
                    Exit('success');
                }
                $sendQuery = new  sendQuery();
                if (trim($status) === '1') {
                    $log_txt = '通知成功';
                    $order_status = 100;
                    $realmoney = intval($order_coin / 1000);
                    $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY", [$order['AccountID'], 1, $orderid, $realmoney, $order_coin]);
                    save_log('fastpay', '发送服务端成功:' . '单号：'.$orderid.',金额：'.$realmoney.',币：'.$order_coin.',返回状态：'.json_encode($res));
                    $text = "success";
                } elseif (trim($status) == '3') {
                    //金币退回
                    $log_txt = '第三方处理失败' . $params['status'];
                    $order_status = 5;
                    $text = "Reject"; //处理失败 Processing failed
                } else {
                    $order_status = 3;
                    $log_txt = '第三方处理中' . $params['status'];
                    $text = "Pending"; //处理失败 Processing failed
                    Exit('success');
                }

                if ($order_status == 5) {
                    $realmoney = intval($order_coin / 1000);
                    $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY", [$order['AccountID'], 2, $orderid, $realmoney, $order_coin]);
                    $res = unpack("Cint", $res)['int'];
                    save_log('fastpay', '发送服务端失败:' . '单号：'.$orderid.',金额：'.$realmoney.',币：'.$order_coin.',返回状态：'.json_encode($res));
                    if ($res != 0) {
                        $log_txt = '第三方处理失败金币未返还';
                    }
                }

                $save_data = [
                    'status' => $order_status,
                    'IsDrawback' => $order_status,
                    'TransactionNo' => $transactionId,
                    'UpdateTime' => date('Y-m-d H:i:s', time())
                ];
                $db = new BankDB('userdrawback');
                $info = $db->updateByWhere(['OrderNo' => $orderid], $save_data);
                $gameoc = new GameOC();
                $data = [
                    'OrderId' => $orderid,
                    'Controller' => 'Notify',
                    'Method' => __METHOD__,
                    'Parameter' => $json,
                    'Error' => $log_txt,
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $gameoc->PaynotifyLog()->Insert($data);
                $text = "success"; //处理成功,返回的标识 The processing is successful, the returned ID

            } else {
                $text = "success"; //签名失败 Signature failed
                $gameoc = new GameOC();
                $data = [
                    'OrderId' => $orderid,
                    'Controller' => 'Notify',
                    'Method' => __METHOD__,
                    'Parameter' => $json,
                    'Error' => $text,
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $gameoc->PaynotifyLog()->Insert($data);
            }
            $this->del_mutex($rdskey);
            exit('success');
        } catch (Exception $ex) {
            //log::INFO('提现通知错误：'.$ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            save_log('fastpay', 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            $gameoc = new GameOC();
            $data = [
                'OrderId' => $orderid,
                'Controller' => 'Notify',
                'Method' => __METHOD__,
                'Parameter' => $json,
                'Error' => $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString(),
                'AddTime' => date('Y-m-d H:i:s', time())
            ];
            $gameoc->PaynotifyLog()->Insert($data);
            exit('success');
        }
    }


    //签名函数
    protected function createSign($list, $Md5key)
    {
        ksort($list); //按照ASCII码排序
        $tempstr = "";
        foreach ($list as $key => $val) {
            if ($val !== '') {
                $tempstr = $tempstr . $key . "=" . $val . "&";
            }
        }
        $md5str = md5($tempstr . "key=" . $Md5key);    //最后拼接上key=ApiKey(你的商户秘钥),进行md5加密
        $sign = strtoupper($md5str);                //把字符串转换为大写，得到sign签名
        return $sign;
    }


    private function set_mutex($read_news_mutex_key, $timeout = 3)
    {
        $cur_time = time();
        $mutex_res = Redis::lock($read_news_mutex_key, $cur_time + $timeout);
        if ($mutex_res) {
            return true;
        }
        //就算意外退出，下次进来也会检查key，防止死锁
        $time = Redis::get($read_news_mutex_key);
        if ($cur_time > $time) {
            Redis::rm($read_news_mutex_key);
            return Redis::lock($read_news_mutex_key, $cur_time + $timeout);
        }
        return false;
    }

    /**
     * @param $uid
     * 释放锁
     */
    private function del_mutex($read_news_mutex_key)
    {
        Redis::rm($read_news_mutex_key);
    }


}