<?php

namespace app\client\controller;

use app\model\BankDB;
use app\model\GameOC;
use app\model\MasterDB;
use app\model\UserDB;
use log\log;
use icbcpay\PaySdk;
use redis\Redis;
use socket\sendQuery;
use think\Controller;
use think\Exception;
use think\facade\Cache;


class IcbcpayNotify extends Controller
{

    public function notify()
    {
        try {
            $params =$_POST;
            $json=json_encode($params);
            save_log('icbcpay', 'icbcpay:' . $json);
            if (empty($params)) {
                exit('Empty Request');
            }

            $orderid = $params['orderid'];
            $realmoney = $params['amount'];
            $transactionId = $params['orderid'];
            $msg = $params['msg'];
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
            $params['appkey'] = $detail['secret'];
            $checksign = $this->genSign($params,$detail['secret']);
            $text = 'success';
            if ($checksign==$sign) { //验签 Verify signature
                $status = $params['code'];
                $log_txt = '';

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
                    exit('success');
                }
                if ($status == 200) {
                    if(strpos($msg,'Your payment is Successful')!==false){
                        $master = new MasterDB();
                        $itemM = $master->getTableRow('T_ShopItem', 'RealMoney=' . $realmoney, '*');
                        $Money = $itemM['BaseGoodsValue']; //实际金币 对应表 masterdb.t_shopitem BaseGoodsValue
                        $sendQuery = new  sendQuery();
                        $res = $sendQuery->callback('CMD_MD_USER_CHANNEL_RECHARGE', [$order['AccountID'], $ChannelId, $transactionId, $detail['currency'], $realmoney, $Money, $order['PayType']]);
                        $code = unpack("LCode", $res)['Code'];
                        save_log('icbcpay', '玩家id:' . $order['AccountID'] . ',订单id:' . $transactionId . ',金币发送状态' . $code);
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
                            $text = 'success2';
                        }
                        $result =$this->SendAppMsg($order['AccountID'],1,$msg,$msg);
                    }
                    else if(strpos($msg,'Payee added successfully')!==false){
                        $result =$this->SendAppMsg($order['AccountID'],1,$msg,$msg);
                        exit('success');
                    }
                } else{



                    $data = [
                        'PayTime' => date('Y-m-d H:i:s', time()),
                        'Status' => 3,
                        'TransactionNo' => $transactionId
                    ];
                    $user = new UserDB();
                    $ret = $user->updateTable('T_UserChannelPayOrder', $data, ['OrderId' => $orderid]);
                    $log_txt = '第三方订单处理失败' . $params['msg'];
                    $text = "success"; //处理失败 Processing failed
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
            save_log('icbcpay', 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            exit('success');
        }
    }


    public function outnotify(){
        try {
            $json = file_get_contents('php://input');
            if (trim($json)=='') {
                exit('Empty Request');
            }
            $params = json_decode($json,true); //POST参数 POST parameters
            save_log('ssspay','payoutnotify:' . $json);
            $orderid = $params['orderID'];
            $status = trim($params['status']);
            $sign = $params['sign'];
            if(isset($params['remark'])){
                unset($params['remark']);
            }
            unset($params['sign']);
            if(empty($orderid) || empty($sign)){
                exit('parameters Error');
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
                exit('OK');
            }

            $masterA = new  MasterDB();
            $channel = $masterA->getTableRow('T_GamePayChannel', ['channelID' => $order['ChannelId']], '*');
            $channelcfg = json_decode($channel['MerchantDetail'], true);
            $checksign = $this->genMD5Sign($params,$channelcfg['secret']);
            if ($checksign==$sign) { //验签 Verify signature
                $order_status =0;
                $log_txt ='';
                $transactionId =$params['platOrderID'];
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

                if ($status ==0) {
                    $log_txt='通知成功';
                    $order_status =100;
                    $realmoney = intval($order_coin/1000);
                    $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY", [$order['AccountID'], 1, $orderid, $realmoney, $order_coin]);
                    $text = "OK";
                }
                else if($status==1){
                    $log_txt='第三方处理失败';
                    $order_status =5;
                    $text = "OK"; //处理失败 Processing failed
                }else{
                    Exit('OK');
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
                $text = "OK"; //处理成功,返回的标识 The processing is successful, the returned ID

            } else {
                $text = "sign Faild"; //签名失败 Signature failed
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
            save_log('ssspay','Exception:' . $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
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


    private function genSign($data,$Md5key)
    {
        ksort($data);
        $md5str = '';
        foreach ($data as $key => $val) {
            if (trim($val)!=='') {
                $md5str = $md5str . $key . '=' . $val . '&';
            }
        }
        $md5str = trim($md5str,'&');
        return strtoupper(md5($md5str));// . 'key=' . $Md5key
    }


    private function SendAppMsg($roleid,$nType,$szFcmTitle,$szFcmMsg)
    {

        $sendQuery = new sendQuery();
        $socket = $sendQuery->callback("CMD_WD_TRANSFORM_FCM", [$roleid,$nType,$szFcmTitle,$szFcmMsg], 'DC');
        // var_dump($socket);die;
        if (empty($socket)) return 0;
        $out_array = unpack('LiResult/', $socket);//   ProcessAWOperateAckRes($out_data);
        return $out_array;
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