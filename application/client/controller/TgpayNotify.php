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

class TgpayNotify extends Controller
{

    public function notify()
    {

        try {
            $params =$_POST?: json_decode(file_get_contents("php://input"),true);

            save_log('tgpay', 'paynotify:' . json_encode($params));
            if (empty($params)) {
                exit('Empty Request');
            }

            $sign = $params['sign'];

            // 验证签名参数是否正确
            unset($params['sign']);
            unset($params['attach']);

            $json = json_encode($params);

            $orderid = $params['orderid'];
            $realmoney = $params['amount'];
            $transactionId = $params['transaction_id'];
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
            $text = 'success';

            if ($sign == $checksign) { //验签 Verify signature
                //订单状态，0：待支付，1：支付成功 Order status, 0: Unpaid, 1: paid
                $status = $params['returncode'];
                $log_txt = '';
                if (trim($status) === '00') {
                    $userdb = new UserDB();
                    $order = $userdb->getTableRow('T_UserChannelPayOrder', ['OrderId' => $orderid], '*');
                    // var_dump($order);die();
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
                    $res = $sendQuery->callback('CMD_MD_USER_CHANNEL_RECHARGE', [$order['AccountID'], $ChannelId, $transactionId, $detail['currency'], $realmoney, $Money, $order['PayType'],$order['Active']]);
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
            }
            exit('OK');
        } catch (Exception $ex) {
            save_log('tgpay', 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            exit('OK');
        }
    }


    public function outnotify(){
        //$params = $_POST; //POST参数 POST parameters
        $params =$_POST?: json_decode(file_get_contents("php://input"),true);
        try {
            save_log('tgpay','代付回调:' . json_encode($params));
            if (empty($params)) {
                exit('Empty Request');
            }
            $json = json_encode($params);
            $sign = $params['sign'];
            // 验证签名参数是否正确
            unset($params['sign']);

            $orderid = $params['out_trade_no'];
            //$amount = $params['transferAmount'];
            $status = $params['refCode'];

            if(empty($orderid) || empty($sign) || empty($status)){
                exit('Empty Request');
            }

            $bankM = new BankDB();
            $order = $bankM->getTableRow('UserDrawBack',['OrderNo'=>$orderid],'AccountID,ChannelId,iMoney,status,DrawBackWay,FreezonMoney,CurWaged,NeedWaged');
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
            $mysign = $this->createSign($params, $channelcfg['secret']); //生成签名 Generate signature
            if ($sign == $mysign) { //验签 Verify signature

                $order_status =0;
                $log_txt ='';
                $transactionId =$params['transaction_id'];
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
                if (trim($status) === '1') {
                    $log_txt='通知成功';
                    $order_status =100;
                    $realmoney = intval($order_coin/1000);
                    $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY_NEW", [$order['AccountID'], 1, $orderid, $realmoney, $order_coin,$order['DrawBackWay'],$order['FreezonMoney'],$order['CurWaged'],$order['NeedWaged']]);
                    $text = "OK";
                    save_log('tgpay','通知服务器状态:' . json_encode($res));
                } elseif (trim($status) == '2' || trim($status) == '5') {
                    //金币退回
                    $log_txt='第三方处理失败'.$params['refCode'];
                    $order_status =5;
                    $text = "Reject"; //处理失败 Processing failed
                }
                else{
                    $order_status =3;
                    $log_txt='第三方处理中'.$params['refCode'];
                    $text = "Pending"; //处理失败 Processing failed
                    Exit('OK');
                }

                $save_data = [
                    'status' => $order_status,
                    'IsDrawback'=>$order_status,
                    'TransactionNo' => $transactionId,
                    'UpdateTime' => date('Y-m-d H:i:s',time())
                ];
                $db = new BankDB('userdrawback');
                $info = $db->updateByWhere(['OrderNo' => $orderid],$save_data);

                if($order_status==5){
                    $realmoney = intval($order_coin/1000);
                    $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY_NEW", [$order['AccountID'], 2, $orderid, $realmoney, $order_coin,$order['DrawBackWay'],$order['FreezonMoney'],$order['CurWaged'],$order['NeedWaged']]);
                    save_log('tgpay','第三方失败:' . $transactionId);
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
                save_log('tgpay','更新订单状态并写日志:' . json_encode($data));
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
            save_log('tgpay','Exception:' . $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
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


    //签名函数
    protected function createSign($list,$Md5key)
    {
        ksort($list); //按照ASCII码排序
        $tempstr = "";
        foreach ($list as $key => $val) {
            if (!empty($val)) {
                $tempstr = $tempstr . $key . "=" . $val . "&";
            }
        }
        $md5str = md5($tempstr . "key=" . $Md5key); 	//最后拼接上key=ApiKey(你的商户秘钥),进行md5加密
        $sign = strtoupper($md5str);				//把字符串转换为大写，得到sign签名
        return $sign;
    }


}