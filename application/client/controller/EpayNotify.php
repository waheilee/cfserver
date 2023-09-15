<?php

namespace app\client\controller;

use app\model\BankDB;
use app\model\GameOC;
use app\model\MasterDB;
use app\model\UserDB;
use log\log;
use redis\Redis;
use socket\sendQuery;
use think\Controller;
use think\Exception;
use think\facade\Cache;
use Utility\Utility;

class EpayNotify extends Controller
{

    public function notify()
    {
        try {
            $json = file_get_contents('php://input');
            if (empty($json)) {
                exit('Empty Request');
            }
            save_log('epay', 'paynotify:' . $json);
            if (!$requestdata = json_decode($json, true)) {
                exit('SUCCESS');
            }
            $params = json_decode($requestdata['content'], true);
            $sign = $params['sign'];
            // 验证签名参数是否正确
            unset($params['sign']);
            $orderid = $params['merchantTradeNo'];
            $realmoney = $params['amount'];
            $transactionId = $params['tradeNo'];
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
                exit('OK');
            }

            $ChannelId = $order['ChannelID'];
            $masterA = new MasterDB();
            $channel = $masterA->getTableRow('T_GamePayChannel', ['channelID' => $ChannelId], '*');
            $detail = json_decode($channel['MerchantDetail'], true);
            $checksign = $this->createSign($params, $detail['secret']);
            $text = 'SUCCESS';
            if ($sign == $checksign) { //验签 Verify signature
                //订单状态，0：待支付，1：支付成功 Order status, 0: Unpaid, 1: paid
                $status = $params['tradeStatus'];
                $log_txt = '';
                if (trim($status) === 'PAYMENT_SUCCESS') {
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
                    save_log('goldpay', '玩家id:' . $order['AccountID'] . ',订单id:' . $transactionId . ',金币发送状态' . $code);
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
                        $text = 'SUCCESS';
                    }

                } else {
                    $data = [
                        'PayTime' => date('Y-m-d H:i:s', time()),
                        'Status' => 3,
                        'TransactionNo' => $transactionId
                    ];
                    $user = new UserDB();
                    $ret = $user->updateTable('T_UserChannelPayOrder', $data, ['OrderId' => $orderid]);
                    $log_txt = '第三方订单处理失败' . $status;
                    $text = "FAIL"; //处理失败 Processing failed
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
            exit('SUCCESS');
        } catch (Exception $ex) {
            save_log('epay', 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            exit('SUCCESS');
        }
    }


    public function outnotify()
    {

        try {

            $json = file_get_contents('php://input');
            if (empty($json)) {
                exit('Empty Request');
            }
            save_log('epay', 'pay_out_notify:' . $json);
            if (!$requestdata = json_decode($json, true)) {
                exit('SUCCESS');
            }

            $params = json_decode($requestdata['content'], true);
            $sign = $params['sign'];
            unset($params['sign']);

            $orderid = $params['merchantTradeNo'];
            $realmoney = $params['amount'];
            $transactionId = $params['tradeNo'];
            $status = $params['tradeStatus'];
            if (empty($orderid) || empty($realmoney) || empty($transactionId)) {
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
                exit('SUCCESS');
            }
            $masterA = new  MasterDB();
            $channel = $masterA->getTableRow('T_GamePayChannel', ['channelID' => $order['ChannelId']], '*');
            $channelcfg = json_decode($channel['MerchantDetail'], true);
            $mysign = $this->createSign($params, $channelcfg['secret']); //生成签名 Generate signature
            if ($sign == $mysign) { //验签 Verify signature

                $order_status = 0;
                $log_txt = '';
                $rdskey = 'outnotify'.$orderid.$status;
                if(!$this->set_mutex($rdskey)){
                    Exit('SUCCESS');
                }
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
                    Exit('SUCCESS');
                }
                $sendQuery = new  sendQuery();
                if (trim($status) === 'WITHDRAWAL_SUCCESS') {
                    $log_txt = '通知成功';
                    $order_status = 100;
                    $realmoney = intval($order_coin / 1000);
                    $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY", [$order['AccountID'], 1, $orderid, $realmoney, $order_coin]);
                    $text = "SUCCESS";
                } elseif (trim($status) == 'WITHDRAWAL_FAILURE') {
                    //金币退回
                    $log_txt = '第三方处理失败';
                    $order_status = 5;
                    $text = "Faild"; //处理失败 Processing failed
                } else {
                    $order_status = 3;
                    $log_txt = '第三方处理中' . $status;
                    $text = "Pending"; //处理失败 Processing failed
                    $this->del_mutex($rdskey);
                    Exit('SUCCESS');
                }

                if ($order_status == 5) {
                    $realmoney = intval($order_coin / 1000);
                    $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY", [$order['AccountID'], 2, $orderid, $realmoney, $order_coin]);
                    $res = unpack("Cint", $res)['int'];
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
            exit('SUCCESS');
        } catch (Exception $ex) {
            //log::INFO('提现通知错误：'.$ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            save_log('epay', 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
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
            exit('SUCCESS');
        }
    }


    //签名函数
    protected function createSign($list, $Md5key)
    {
        ksort($list); //按照ASCII码排序
        $tempstr = "";
        foreach ($list as $key => $val) {
            if (!empty($val)) {
                $tempstr = $tempstr . $val;
            }
        }
        $md5str = md5($tempstr . $Md5key);    //最后拼接上key=ApiKey(你的商户秘钥),进行md5加密
        $sign = strtolower($md5str);                //把字符串转换为大写，得到sign签名
        return $sign;
    }



    private  function set_mutex($read_news_mutex_key,$timeout = 2){
        $cur_time = time();
        $mutex_res = Redis::lock($read_news_mutex_key,$cur_time + $timeout);
        if($mutex_res){
            return true;
        }
        //就算意外退出，下次进来也会检查key，防止死锁
        $time = Redis::get($read_news_mutex_key);
        if($cur_time > $time){
            Redis::rm($read_news_mutex_key);
            return Redis::lock($read_news_mutex_key,$cur_time + $timeout);
        }
        return false;
    }

    /**
     * @param $uid
     * 释放锁
     */
    private  function del_mutex($read_news_mutex_key){
        Redis::rm($read_news_mutex_key);
    }


}