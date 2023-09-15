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

class Notify extends Controller
{

    public function tPayNotify()
    {
        $param = $_POST;
        try {
            save_log('tppay', 'notify:' . json_encode($param));
            if (empty($param)) {
                exit('Empty Request');
            }
            $orderid = $param['order'];
            $uid = $param['uid'];
            $gid = $param['gid'];
            $amount = $param['amount'];
            $transactionId = $param['transaction_id'];
            $sn = $param['sn'];

            if (empty($orderid) || empty($amount) || empty($sn) || empty($transactionId)) {
                exit('Empty Request');
            }

            unset($param['sn']);
            $db = new UserDB();
            $order = $db->getTableRow('T_UserChannelPayOrder', ['OrderId' => $orderid], '*');
            if (empty($order)) {
                $gameoc = new GameOC();
                $data = [
                    'OrderId' => $orderid,
                    'Controller' => 'Notify',
                    'Method' => __METHOD__,
                    'Parameter' => json_encode($param),
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
            $sign = $this->genSn($param, $detail['secret']);
            $text = 'success';
            if ($sign == $sn) {
                $log_txt = '';
                if ($order['Status'] != 0) {
                    $gameoc = new GameOC();
                    $data = [
                        'OrderId' => $orderid,
                        'Controller' => 'Notify',
                        'Method' => __METHOD__,
                        'Parameter' => json_encode($param),
                        'Error' => '订单已经支付或其他状态',
                        'AddTime' => date('Y-m-d H:i:s', time())
                    ];
                    $gameoc->PaynotifyLog()->insert($data);
                    exit($text);
                }
                $masterB = new MasterDB();
                $res = $masterB->getTableRow('T_ShopItem', 'RealMoney=' . $amount, '*');
                if ($res) {
                    $Money = $res['BaseGoodsValue']; //实际金币 对应表 masterdb.t_shopitem BaseGoodsValue
                    $sendQuery = new  sendQuery();
                    $resp = $sendQuery->callback('CMD_MD_USER_CHANNEL_RECHARGE', [$order['AccountID'], $ChannelId, $transactionId, $detail['currency'], $amount, $Money]);
                    $code = unpack("LCode", $resp)['Code'];
                    if (intval($code) === 0) {
                        $data = [
                            'PayTime' => date('Y-m-d H:i:s', time()),
                            'Status' => 1,
                            'TransactionNo' => $transactionId
                        ];
                        $user = new UserDB();
                        $ret = $user->updateTable('T_UserChannelPayOrder', $data, ['OrderId' => $orderid]);
                        if ($ret)
                            $log_txt = '订单充值成功';
                        else
                            $log_txt = '充值成功，订单状态更新失败';

                    } else {//上分失败
                        $data = [
                            'PayTime' => date('Y-m-d H:i:s', time()),
                            'Status' => 2,
                            'TransactionNo' => $transactionId
                        ];
                        $user = new UserDB();
                        $ret = $user->updateTable('T_UserChannelPayOrder', $data, ['OrderId' => $orderid]);
                        if ($ret)
                            $log_txt = '金币未发放成功';
                        else
                            $log_txt = '金币未发放成功,订单状态更新失败';

                    }
                } else {

                    $data = [
                        'PayTime' => date('Y-m-d H:i:s', time()),
                        'Status' => 2,
                        'TransactionNo' => $transactionId
                    ];
                    $user = new UserDB();
                    $ret = $user->updateTable('T_UserChannelPayOrder', $data, ['OrderId' => $orderid]);
                    if ($ret)
                        $log_txt = '充值成功，金币未发放';
                    else
                        $log_txt = '金币未发放,订单状态更新失败';

                }
                $gameoc = new GameOC();
                $data = [
                    'OrderId' => $orderid,
                    'Controller' => 'Notify',
                    'Method' => __METHOD__,
                    'Parameter' => json_encode($param),
                    'Error' => $log_txt,
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $gameoc->PaynotifyLog()->insert($data);
            } else {
                $gameoc = new GameOC();
                $data = [
                    'OrderId' => $orderid,
                    'Controller' => 'Notify',
                    'Method' => __METHOD__,
                    'Parameter' => json_encode($param),
                    'Error' => '签名错误',
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $gameoc->PaynotifyLog()->insert($data);
            }
            exit($text);
        }catch (Exception $ex){
            save_log('tppay','Exception:' . $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            exit('success');
        }
    }


    public function tPayOutNotify()
    {
        $params = $_POST; //POST参数 POST parameters
        try {
            save_log('tppay','payoutnotify:' . json_encode($params));
            if(empty($params)){
                exit('Empty Request');
            }

            $orderid = $params['order'];
            $amount = $params['amount'];
            $completeTime = $params['completeTime'];
            $status = $params['status'];
            $sign = $params['sn'];
            unset($params['sn']);

            if(empty($orderid) || empty($amount) || empty($sign) || empty($status)){
                exit('Empty Request');
            }

            $bankM = new BankDB();
            $order = $bankM->getTableRow('UserDrawBack',['OrderNo'=>$orderid],'AccountID,ChannelId,iMoney,status');
            if (empty($order)) {
                $gameoc = new GameOC();
                $data = [
                    'OrderId' => $orderid,
                    'Controller' => 'Notify',
                    'Method' => __METHOD__,
                    'Parameter' => json_encode($params),
                    'Error' => '订单不存在',
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $gameoc->PaynotifyLog()->insert($data);
                exit('success');
            }

            $masterA = new  MasterDB();
            $channel = $masterA->getTableRow('T_GamePayChannel', ['channelID' => $order['ChannelId']], '*');
            $channelcfg = json_decode($channel['MerchantDetail'], true);
            $mysign = $this->genSn($params, $channelcfg['secret']); //生成签名 Generate signature
            if ($sign == $mysign) { //验签 Verify signature

                $order_status =0;
                $log_txt ='';
                $transactionId ='RE'.$orderid;
                $order_coin = intval($order['iMoney']);
                if($order['status']!=4){
                    $log_txt='订单状态不正确，非订单已经提交状态';
                    $gameoc = new GameOC();
                    $data =[
                        'OrderId'=>$orderid,
                        'Controller'=>'tPayNotify',
                        'Method' => __METHOD__,
                        'Parameter'=>json_encode($params),
                        'Error'=>$log_txt,
                        'AddTime' => date('Y-m-d H:i:s',time())
                    ];
                    $gameoc->PaynotifyLog()->insert($data);
                    Exit('Status Error');
                }
                $sendQuery=new  sendQuery();
                $status = intval($status);
                if ($status === 4) {
                    $log_txt='通知成功';
                    $order_status =100;
                    $realmoney = intval($order_coin/1000);
                    $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY", [$order['AccountID'], 1, $orderid, $realmoney, $order_coin]);
                    $text = "success";
                } elseif ($status === 5) {
                    //金币退回
                    $log_txt='第三方处理失败';
                    $order_status =5;
                    $text = "Reject"; //处理失败 Processing failed
                } else {
                    $order_status =5;
                    $log_txt='第三方处理失败';
                    $text = "Pending"; //处理失败 Processing failed
                }

                if($order_status==5){
                    $realmoney = intval($order_coin/1000);
                    $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY", [$order['AccountID'], 2, $orderid, $realmoney, $order_coin]);
                    $res = unpack("Cint", $res)['int'];
                    if ($res != 0){
                        $log_txt='第三方处理失败金币未返还';
                    }
                }

                $save_data = [
                    'status' => $order_status,
                    'IsDrawback'=>$order_status,
                    'TransactionNo' => $transactionId,
                    'UpdateTime' => date('Y-m-d H:i:s', $completeTime)
                ];
                $db = new BankDB('userdrawback');
                $info = $db->updateByWhere(['OrderNo' => $orderid],$save_data);
                $gameoc = new GameOC();
                $data =[
                    'OrderId'=>$orderid,
                    'Controller'=>'tPayOutNotify',
                    'Method' => __METHOD__,
                    'Parameter'=>json_encode($params),
                    'Error'=>$log_txt,
                    'AddTime' => date('Y-m-d H:i:s',time())
                ];
                $gameoc->PaynotifyLog()->Insert($data);
                $text = "success"; //处理成功,返回的标识 The processing is successful, the returned ID

            } else {
                $text = "success"; //签名失败 Signature failed
                $gameoc = new GameOC();
                $data =[
                    'OrderId'=>$orderid,
                    'Controller'=>'tPayOutNotify',
                    'Method' => __METHOD__,
                    'Parameter'=>json_encode($params),
                    'Error'=>$text,
                    'AddTime' => date('Y-m-d H:i:s',time())
                ];
                $gameoc->PaynotifyLog()->Insert($data);
            }
            exit('success');
        }catch (Exception $ex){
            //log::INFO('提现通知错误：'.$ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            save_log('tppay','Exception:' . $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            $gameoc = new GameOC();
            $data =[
                'OrderId'=>$orderid,
                'Controller'=>'tPayOutNotify',
                'Method' => __METHOD__,
                'Parameter'=>json_encode($params),
                'Error'=>$ex->getMessage().$ex->getLine().$ex->getTraceAsString(),
                'AddTime' => date('Y-m-d H:i:s',time())
            ];
            $gameoc->PaynotifyLog()->Insert($data);
            exit('success');
        }

    }


    private function genSn($GET, $secret)
    {
        ksort($GET);
        $arr = [];
        foreach ($GET as $k => $v) {
            $arr[] = "{$k}=" . urlencode($v);
        }
        $arr[] = "secret={$secret}";
        return md5(join('', $arr));
    }


    public function easyPayNotify(){
        $json = file_get_contents('php://input');
        try {
            save_log('easypay', 'paynotify:' . $json);
            if (empty($json)) {
                exit('Empty Request');
            }
            if (!$params = json_decode($json, true)) {
                exit('success');
            }

            $sign = $params['sign'];
            // 验证签名参数是否正确
            unset($params['sign'], $params['attach'], $params['time']);
//            if ($sign != $this->genSign($params)) {
//                exit('OK');
//            }
//
//            // 返回大写 OK 说明接受到系统回调了  不管是成功/失败 都请返回OK给我们
//            switch ($params['code']) {
//                case 1:
//                    // 处理成功流程
//                    exit('OK');
//                case 2:
//                    // 处理失败流程
//                    exit('OK');
//                default:
//                    exit('OK');
//            }

            $orderid = $params['outTradeNo'];
            $realmoney = $params['coin'];
            $transactionId ='EASYPAY'.$params['outTradeNo'];
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
            $checksign = $this->geneasySign($params, $detail['secret']);
            $text = 'OK';
            if ($sign == $checksign) { //验签 Verify signature
                //订单状态，0：待支付，1：支付成功 Order status, 0: Unpaid, 1: paid
                $status = isset($params['code']) ? (int)$params['code'] : 0;
                $log_txt = '';
                if ($status === 1) {
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
                    $res = $sendQuery->callback('CMD_MD_USER_CHANNEL_RECHARGE', [$order['AccountID'], $ChannelId, $transactionId, $detail['currency'],$realmoney, $Money,3]);
                    $code = unpack("LCode", $res)['Code'];
                    save_log('easypay', '玩家id:' . $order['AccountID'] . ',订单id:' . $transactionId . ',金币发送状态' . $code);
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
                        $text = 'OK';
                    }

                } else {
                    $data = [
                        'PayTime' => date('Y-m-d H:i:s', time()),
                        'Status' => 3,
                        'TransactionNo' => $transactionId
                    ];
                    $user = new UserDB();
                    $ret = $user->updateTable('T_UserChannelPayOrder', $data, ['OrderId' => $orderid]);
                    $log_txt = '第三方订单处理失败';
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
            exit('OK');
        }catch (Exception $ex){
            save_log('easypay','Exception:' . $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            exit('OK');
        }
    }

    public function redicturl(){

        return 'success';
    }


    public function easyPayoutNotify(){
        $json = file_get_contents('php://input');
        try {
            save_log('easypay','payoutnotify:' . $json);
            if (empty($json)) {
                exit('Empty Request');
            }
            if (!$params = json_decode($json, true)) {
                exit('success');
            }

            $sign = $params['sign'];
            // 验证签名参数是否正确
            unset($params['sign'], $params['attach'], $params['time']);

            $orderid = $params['outTradeNo'];
            $amount = $params['coin'];
            $status = $params['code'];

            if(empty($orderid) || empty($amount) || empty($sign) || empty($status)){
                exit('Empty Request');
            }

            $bankM = new BankDB();
            $order = $bankM->getTableRow('UserDrawBack',['OrderNo'=>$orderid],'AccountID,ChannelId,iMoney,status');
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
            $mysign = $this->geneasySign($params, $channelcfg['secret']); //生成签名 Generate signature
            if ($sign == $mysign) { //验签 Verify signature

                $order_status =0;
                $log_txt ='';
                $transactionId ='RE'.$orderid;
                $order_coin = intval($order['iMoney']);
                if($order['status']!=4){
                    $log_txt='订单状态不正确，非订单已经提交状态';
                    $gameoc = new GameOC();
                    $data =[
                        'OrderId'=>$orderid,
                        'Controller'=>'Notify',
                        'Method' => __METHOD__,
                        'Parameter'=>$json,
                        'Error'=>$log_txt,
                        'AddTime' => date('Y-m-d H:i:s',time())
                    ];
                    $gameoc->PaynotifyLog()->insert($data);
                    Exit('OK');
                }
                $sendQuery=new  sendQuery();
                $status = intval($status);
                if ($status === 1) {
                    $log_txt='通知成功';
                    $order_status =100;
                    $realmoney = intval($order_coin/1000);
                    $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY", [$order['AccountID'], 1, $orderid, $realmoney, $order_coin]);
                    $text = "OK";
                } elseif ($status === 2) {
                    //金币退回
                    $log_txt='第三方处理失败';
                    $order_status =5;
                    $text = "Reject"; //处理失败 Processing failed
                } else {
                    $order_status =5;
                    $log_txt='第三方处理中';
                    $text = "Pending"; //处理失败 Processing failed
                }

                if($order_status==5){
                    $realmoney = intval($order_coin/1000);
                    $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY", [$order['AccountID'], 2, $orderid, $realmoney, $order_coin]);
                    $res = unpack("Cint", $res)['int'];
                    if ($res != 0){
                        $log_txt='第三方处理失败金币未返还';
                    }
                }

                $save_data = [
                    'status' => $order_status,
                    'IsDrawback'=>$order_status,
                    'TransactionNo' => $transactionId,
                    'UpdateTime' => date('Y-m-d H:i:s',time())
                ];
                $db = new BankDB('userdrawback');
                $info = $db->updateByWhere(['OrderNo' => $orderid],$save_data);
                $gameoc = new GameOC();
                $data =[
                    'OrderId'=>$orderid,
                    'Controller'=>'Notify',
                    'Method' => __METHOD__,
                    'Parameter'=>$json,
                    'Error'=>$log_txt,
                    'AddTime' => date('Y-m-d H:i:s',time())
                ];
                $gameoc->PaynotifyLog()->Insert($data);
                $text = "success"; //处理成功,返回的标识 The processing is successful, the returned ID

            } else {
                $text = "success"; //签名失败 Signature failed
                $gameoc = new GameOC();
                $data =[
                    'OrderId'=>$orderid,
                    'Controller'=>'Notify',
                    'Method' => __METHOD__,
                    'Parameter'=>$json,
                    'Error'=>$text,
                    'AddTime' => date('Y-m-d H:i:s',time())
                ];
                $gameoc->PaynotifyLog()->Insert($data);
            }
            exit('OK');
        }catch (Exception $ex){
            //log::INFO('提现通知错误：'.$ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            save_log('easypay','Exception:' . $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            $gameoc = new GameOC();
            $data =[
                'OrderId'=>$orderid,
                'Controller'=>'Notify',
                'Method' => __METHOD__,
                'Parameter'=>$json,
                'Error'=>$ex->getMessage().$ex->getLine().$ex->getTraceAsString(),
                'AddTime' => date('Y-m-d H:i:s',time())
            ];
            $gameoc->PaynotifyLog()->Insert($data);
            exit('OK');
        }
    }


    private function geneasySign($data,$Md5key)
    {
        ksort($data);
        $md5str = '';
        foreach ($data as $key => $val) {
            if (!empty(trim($val))) {
                $md5str = $md5str . $key . '=' . $val . '&';
            }
        }
        return strtoupper(md5($md5str . 'key=' . $Md5key));
    }

}