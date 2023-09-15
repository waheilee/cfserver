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

class IndipayNotify extends Controller
{

    public function notify()
    {
        try {
            $params =$_GET;
            save_log('indipay', '代收通知:' . json_encode($params));
            if (empty($params)) {
               return json(['result'=>'success','message'=>'empty request']);
            }
            $json =json_encode($params);
            $sign = $params['sign'];
            $parameter = $params['parameter'];

            $orderid = $params['commercialOrderNo'];
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
                return json(['result'=>'success','message'=>'order not exists']);
            }

            $ChannelId = $order['ChannelID'];
            $masterA = new MasterDB();
            $channel = $masterA->getTableRow('T_GamePayChannel', ['channelID' => $ChannelId], '*');
            $detail = json_decode($channel['MerchantDetail'], true);
            $bizjson= $this->decrypt($parameter,$detail['secret']);

            if(!$bizjson){
                return json(['result'=>'success','message'=>'notify parameter empty']);
            }

            $bizdata = json_decode($bizjson,true);
            $realmoney = $bizdata['orderAmount'];
            $transactionId = $bizdata['orderNo'];
            $status = $bizdata['result'];
            if (empty($orderid) || empty($realmoney) || empty($transactionId)) {
                return json(['result'=>'success','message'=>'decrpt parameter empty.']);
            }

            $checksign = md5($bizjson);
            $text = 'success';
            if ($sign == $checksign) { //验签 Verify signature
                //订单状态，0：待支付，1：支付成功 Order status, 0: Unpaid, 1: paid
                $log_txt = 'success';
                if (trim($status) === 'success') {
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
                        return json(['result'=>'success','message'=>$text]);
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
                        $text = 'OK';
                    }

                } else{
                    $data = [
                        'PayTime' => date('Y-m-d H:i:s', time()),
                        'Status' => 3,
                        'TransactionNo' => $transactionId
                    ];
                    $user = new UserDB();
                    $ret = $user->updateTable('T_UserChannelPayOrder', $data, ['OrderId' => $orderid]);
                    $log_txt = '第三方订单处理失败' . $params['returncode'];
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
                return json(['result'=>'success','message'=>'sign check error']);
            }
            return json(['result'=>'success','message'=>'successful']);
        } catch (Exception $ex) {
            save_log('indipay', 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            return json(['result'=>'success','message'=>'exception occur']);
        }
    }


    public function outnotify(){
        $params = $_GET; //POST参数 POST parameters
        try {
            if (empty($params)) {
                exit('Empty Request');
            }
            $json = json_encode($params);
            $sign = $params['sign'];
            // 验证签名参数是否正确
            unset($params['sign']);
            $parameter = $params['parameter'];
            $bizjson = $this->decrypt($parameter,'24B0D0EC44CCFD9CAEE827353206F137');
            if(!$bizjson){
                return json(['result'=>'success','message'=>'notify parameter empty']);
            }
            save_log('indipay','代付回调:' . json_encode($params).$bizjson);
            $bizdata = json_decode($bizjson,true);

            $orderid = $bizdata['outTradeNo'];
            $amount = $bizdata['totalAmount'];
            $status = $bizdata['result'];
            $transactionId =$bizdata['tradeNo'];

            if(empty($orderid) || empty($sign) || empty($status)){
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
                return json(['result'=>'success','message'=>'order not exists']);
            }
//            $masterA = new  MasterDB();
//            $channel = $masterA->getTableRow('T_GamePayChannel', ['channelID' => $order['ChannelId']], '*');
//            $channelcfg = json_decode($channel['MerchantDetail'], true);
            $mysign = md5($bizjson);
            if ($sign == $mysign) { //验签 Verify signature

                $order_status =0;
                $log_txt ='';
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
                    return json(['result'=>'success','message'=>'order status is not right']);
                }
                $sendQuery=new  sendQuery();
                if (trim($status) == 'success') {
                    $log_txt='通知成功';
                    $order_status =100;
                    $realmoney = intval($order_coin/1000);
                    $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY", [$order['AccountID'], 1, $orderid, $realmoney, $order_coin]);
                    $text = "OK";
                    save_log('indipay','通知服务器状态:' . json_encode($res));
                } else{
                    //金币退回
                    $log_txt='第三方处理失败'.$params['refCode'];
                    $order_status =5;
                    $text = "Reject"; //处理失败 Processing failed
                }

                $save_data = [
                    'status' => $order_status,
                    'IsDrawback'=>$order_status,
                    'TransactionNo' => $transactionId,
                    'UpdateTime' => date('Y-m-d H:i:s',time())
                ];
                $db = new BankDB('userdrawback');
                $db->updateByWhere(['OrderNo' => $orderid],$save_data);

                if($order_status==5){
                    $realmoney = intval($order_coin/1000);
                    $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY", [$order['AccountID'], 2, $orderid, $realmoney, $order_coin]);
                    $res = unpack("Cint", $res)['int'];
                    if ($res != 0){
                        $log_txt='第三方处理失败金币未返还';
                    }
                }

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
                save_log('indipay','更新订单状态并写日志:' . json_encode($data));
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
            return json(['result'=>'success','message'=>'successful']);
        }catch (Exception $ex){
            //log::INFO('提现通知错误：'.$ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            save_log('indipay','Exception:' . $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
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
            return json(['result'=>'success','message'=>'exception occur']);
        }
    }


    private function encrypt($message, $key,$methon='AES-256-ECB')
    {
        if (mb_strlen($key, '8bit') !== 32) {
            throw new Exception("Needs a 256-bit key!");
        }
        $ivsize = openssl_cipher_iv_length($methon);
        $iv     = openssl_random_pseudo_bytes($ivsize);
        $ciphertext = openssl_encrypt(
            $message,
            $methon,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        return base64_encode($iv . $ciphertext);
    }

    private function decrypt($message, $key,$methon='AES-256-ECB')
    {
        if (mb_strlen($key, '8bit') !== 32) {
            throw new Exception("Needs a 256-bit key!");
        }
        $message    = base64_decode($message);
        $ivsize     = openssl_cipher_iv_length($methon);
        $iv         = mb_substr($message, 0, $ivsize, '8bit');
        $ciphertext = mb_substr($message, $ivsize, null, '8bit');
        return openssl_decrypt(
            $ciphertext,
            $methon,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }


}