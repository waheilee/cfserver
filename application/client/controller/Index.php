<?php

namespace app\client\controller;

use app\model\AccountDB;
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
use thaisms\ThaiSms;
use tpay\PaySdk;
use fmpay\FmPaySdk;
use easypay\PaySdk as EasySdk;
use goldpay\PaySdk as GoldSdk;
use sepropay\PaySdk as SeproPay;
use aupay\PaySdk as AuPay;
use gdpaid\GDSdk;
use doipa\PaySdk as DoiPay;
use beepay\PaySdk as BeePay;
use payplus\PaySdk as PayPlus;
use inpays\PaySdk as InPay;
use serpay\PaySdk as SerPay;
use wepay\PaySdk as WePay;
use dypay\PaySdk as DyPay;
use ssspay\PaySdk as SSSPay;
use abcpay\PaySdk as ABCPay;
use icbcpay\PaySdk as ICBCPay;
use swiftpay\PaySdk as SwiftPay;
use epay\PaySdk as EpayPay;
use fastpay\PaySdk as fastpay;
use xpay\PaySdk as Xpay;
use wodeasy\PaySdk as WodEasyPay;
use hwepay\PaySdk as Hwepay;
use hwpay\PaySdk as Hwpay;
use joypay\PaySdk as Joypay;
use tpays\PaySdk as Tpays;
use winpay\PaySdk as Winpay;
use ydpay\PaySdk as Ydpay;
use xjpay\PaySdk as Xjpay;
use Utility\Utility;
use paasoo\SmsSdk;
use indiasms\SmsHelper;
use tgpay\PaySdk as TgPay;
use indipay\PaySdk as IndiPay;
use mail\MailSdk;



class Index extends Controller
{

    public function  index(){
//        $db = new GameOC();
//        $row = $db->getTableRow('T_MailConfig', ['id' => 1], '*');
//
//        var_dump($row);

//        $default_timezone=config('default_timezone');
//        echo $default_timezone;


    }


    //登录客服
    public function customService(){
        try{
            $db = new MasterDB();
            $key ='CustomService::LoginPage';
            $linkurl = Redis::get($key);
            if(empty($linkurl)){
                $customurl = $db->getTableRow('T_CustomService_Cfg', ['VipLabel' => 2,'Status'=>1], 'Phone');
                if(!empty($customurl)){
                    $linkurl =[$customurl['Phone']];
                    Redis::set($key,$linkurl);
                }
            }
            Utility::response(0, 'success',$linkurl);
        }
        catch (Exception $ex){
            return Utility::response(-100, 'The custom service link requst error.');
        }
    }

    //ajust 配置输出给客户端
    public function ajustConfig(){
        $gameoc =new GameOC();
        $result = $gameoc->getTableObject('T_AjustConfig')->select();
        $data =[];
        foreach ($result as $k=>$v){
            $data[$v['channel_id']] =[
                    'aj_first_open'=> $v['aj_first_open'],
                    'aj_first_recharge'=> $v['aj_first_recharge'],
                    'aj_recharge'=> $v['aj_recharge'],
                    'aj_register'=> $v['aj_register']
            ];
        }
        return json($data);
    }

    //发送验证码
    public function SendSMS()
    {
        try {
            $request = $this->request;
            $params = Utility::request(['mobile','type']);
            save_log('sms','get data:' . json_encode($params));
            if ($request->isPost() && !empty($params["mobile"])  && !empty($params["type"])) {
                //防刷 60秒请求一次
                if (Cache::has($params["mobile"])){
                    Utility::response(0, 'Requests are too frequent. Please wait 60 seconds and try again');
                }

                $mobile = $params["mobile"];
                $type =$params["type"];
                $daily_send= Redis::get($mobile);
                if($daily_send){
                    $date= $daily_send['date'];
                    $today = date('Y-m-d',time());
                    $times =$daily_send['times'];
                    if($date==$today){
                        if($times>=5){
                            Utility::response(0, "You have reached today's number of requests. Please try again tomorrow!");
                        }
                    }
                }

                if(preg_match('/([\w\-]+\@[\w\-]+\.[\w\-]+)/',$mobile)){
                    $db = new GameOC();
                    $row = $db->getTableRow('T_MailConfig', ['id' => 1], '*');
                    //开始发送
                    $mailsdk =new MailSdk();
                    $mailsdk->SendBindMail($params["mobile"],$row,$params['type']);
                }else
                {
                    $area = substr($mobile, 0, 2);
                    switch ($area) {
                        case "66":
                            $thaismsM = new ThaiSms();
                            $thaismsM->SendSms($mobile,$type);
                            break;
                        case "91":
                            $smssdk =new SmsHelper();
                            $smssdk->SendSms($mobile,$type);
                            break;
                        case "55":
                            $smssdk =new SmsHelper();
                            $smssdk->SendSms($mobile,$type);
                            break;
                        default:
                            Utility::response(0, 'The region cannot receive the Verification Code!');
                            break;
                    }
                }
            }
        } catch (Exception $ex) {
            //Log::init('SMS-', $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            save_log('sms','Exception:' . $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            return Utility::response(-100, 'The Verification Code requst error.');
        }
    }



    //未登陆发送验证码
    public function SendLoginSMS()
    {
        try {
            $request = $this->request;
            $params = Utility::request(['mobile','type']);
            save_log('loginsms','get data:' . json_encode($params));
            if ($request->isPost() && !empty($params["mobile"])  && !empty($params["type"])) {
                //防刷 60秒请求一次
                if (Cache::has($params["mobile"])){
                    Utility::response(0, 'Requests are too frequent. Please wait 60 seconds and try again');
                }

                $mobile = $params["mobile"];
                $type =$params["type"];
                $daily_send= Redis::get($mobile);
                if($daily_send){
                    $date= $daily_send['date'];
                    $today = date('Y-m-d',time());
                    $times =$daily_send['times'];
                    if($date==$today){
                        if($times>=5){
                            Utility::response(0, "You have reached today's number of requests. Please try again tomorrow!");
                        }
                    }
                }

                if(preg_match('/([\w\-]+\@[\w\-]+\.[\w\-]+)/',$mobile)){
                    $db = new GameOC();
                    $row = $db->getTableRow('T_MailConfig', ['id' => 1], '*');
                    //开始发送
                    $mailsdk =new MailSdk();
                    $mailsdk->SendLoginMail($params["mobile"],$row,$params['type']);
                }
                else{
                    $area = substr($mobile, 0, 2);
                    switch ($area) {
//                    case "66":
//                        $thaismsM = new ThaiSms();
//                        $thaismsM->SendSms($mobile,$type);
//                        break;
                        case "91":
                            $smssdk =new SmsHelper();
                            $smssdk->SendLoginSms($mobile,$type);
                            break;
                        case "55":
                            $smssdk =new SmsHelper();
                            $smssdk->SendLoginSms($mobile,$type);
                            break;
                        default:
                            Utility::response(0, 'The region cannot receive the Verification Code!');
                            break;
                    }
                }
            }
        } catch (Exception $ex) {
            //Log::init('SMS-', $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            save_log('sms','Exception:' . $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            return Utility::response(-100, 'The Verification Code requst error.');
        }
    }




    public function  SendMail(){
        try {
            $request = $this->request;
            $params = Utility::request(['mobile','type']);
            save_log('mail','get data:' . json_encode($params));
            if ($request->isPost() && !empty($params["mobile"])  && !empty($params["type"])) {
                //防刷 60秒请求一次
                if (Cache::has($params["mobile"])){
                    Utility::response(0, 'Requests are too frequent. Please wait 60 seconds and try again');
                }

                $mobile = $params["mobile"];
                $daily_send= Redis::get($mobile);
                if($daily_send){
                    $date= $daily_send['date'];
                    $today = date('Y-m-d',time());
                    $times =$daily_send['times'];
                    if($date==$today){
                        if($times>=1000){
                            Utility::response(0, "You have reached today's number of requests. Please try again tomorrow!");
                        }
                    }
                }
                $db = new GameOC();
                $row = $db->getTableRow('T_MailConfig', ['id' => 1], '*');
                //开始发送
                $mailsdk =new MailSdk();
                $mailsdk->SendLoginMail($params["mobile"],$row,$params['type']);
            }
        } catch (Exception $ex) {
            //Log::init('SMS-', $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            save_log('mail','Exception:' . $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            return Utility::response(-100, 'The Verification Code requst error.');
        }


    }


    public function  payOrder(){
        try {
            $params = Utility::request(['channelID', 'roleid', 'amount', 'time', 'sign', 'ordertype','active']);
            $UserID = $params["roleid"];
            $ChannelId = $params['channelID'];//111;
            $RealMoney = $params['amount'];//实际金额 对应表 masterdb.t_shopitem  RealMoney
            $time = $params['time'];
            $ordertype = $params['ordertype'];
            $date = date("YmdHis");

            save_log('order', '接收参数：' . json_encode($params));
            $master = new MasterDB();
            $itemM = $master->getTableRow('T_ShopItem', 'RealMoney=' . $RealMoney, '*');
            $Money = $itemM['BaseGoodsValue']; //实际金币 对应表 masterdb.t_shopitem BaseGoodsValue
            $sendQuery = new  sendQuery();
            //$UserID, $ChannelId, $Torder,$CurrencyType,$RealMoney,$Money,$ChargeType
            $str_order = rand(1000, 9999);
            $res = $sendQuery->callback('CMD_MD_USER_CHANNEL_RECHARGE', [$UserID, $ChannelId, 'TP10000' . $str_order, 'br', $RealMoney, $Money, $ordertype,$params['active']??1]);
            $code = unpack("LCode", $res)['Code'];
            save_log('order', '通知状态:' . $code);
            return Utility::apiReturn(0, '', 'pay success');
        }catch (Exception $ex){
            save_log('order', '通知状态:' . $ex->getMessage().$ex->getLine().$ex->getLine());
        }

    }


    ///接口暂时不用了
    public function MakeOrder() {
        // try {
            $params = [];
            // $params = Utility::request(['channelID', 'roleid', 'amount','ordertype', 'time', 'sign']);
            $UserID = $params["roleid"]??'123';
            $ChannelId = $params['channelID']??'2600';//111;
            $RealMoney = $params['amount']??100;//实际金额 对应表 masterdb.t_shopitem  RealMoney
            $time = $params['time']??time();
            $ordertype = $params['ordertype']??'';
            $date = date("YmdHis");

            $db = new MasterDB();
            $channel = $db->getTableRow('T_GamePayChannel', ['channelID' => $ChannelId], '*');

            if (!$channel) {
                return Utility::apiReturn(101, '', 'the channel is not exists');
            }

            $rancode = sprintf('%04d',rand(0,9999));
            $order_id = 'TP'.$ChannelId.$date.$rancode;
            $detailconfig =str_replace('/\s/','',$channel['MerchantDetail']);
            $detail =json_decode($channel['MerchantDetail'],true);
//            $rate = $channel['PayRate'];
//
//            if ($rate > 0) {
//                $RealMoney = $RealMoney / $rate;
//            }
            if (intval($channel['MinMoney']) > $RealMoney) {
                return Utility::apiReturn(102, '', 'The payment amount is less than the minimum configuration amount of the channel.');
            }

            if (intval($channel['MaxMoney']) < $RealMoney) {
                return Utility::apiReturn(102, '', 'The payment amount is greater than the maximum configured amount of the channel.');
            }

            if (!$channel['Status']) {
                return Utility::apiReturn(103, '', 'The Payment channel closed.');
            }
            $channelcode = $channel['ChannelCode'];
            $save_data=[
                'ChannelID'=> $ChannelId,
                'OrderId' => $order_id,
                'AccountID'=> $UserID,
                'RealMoney' =>$RealMoney,
                'PayType' => $ordertype,
                'Status' =>0,
                'AddTime' => date('Y-m-d H:i:s',time())
            ];

            $userdb = new UserDB();
            $info = $userdb->TUserChannelPayOrder()->Insert($save_data);
            if(!$info)
                return Utility::apiReturn(104, '', 'Failed to add order data.');

            switch ($channelcode) {
                case 'bananapay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $extra = json_encode(['channelid' =>$ChannelId]);
                    $ret = $this->bananaPay($order_id, $RealMoney, $detail,$extra);
                    if($ret){
                        $data['url'] = $ret['payurl'];
                        $transctionid = $ret['transactionId'];
                        if($transctionid){
                            //$sql ="update T_UserChannelPayOrder set TransactionNo='".$transctionid."' where OrderId='".$order_id."'";
                            $db= new UserDB();
                            $state =  $db->TUserChannelPayOrder()->UPData(['TransactionNo'=>$transctionid], ['OrderId'=>$order_id]);

                        }
                    }
                    break;

                case 'tpay9':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $paysdk = new PaySdk();
                    $param =[
                        'ip' => getClientIP(),
                        'roleid' => $UserID,
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney
                    ];
                    $result = $paysdk->pay($param,$detail);
                    if($result){
                        $data['url'] = $result['d']['h5'];
                    }
                    break;
                case 'fmpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';

                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney
                    ];
                    $fmpay = new FmPaySdk();
                    $result = $fmpay->pay($param,$detail);
                    if(stripos($result,'https://')>=0){
                        $data['url'] = $result;//$result['pay_url'];
                        //$transctionid = $result['trade_no'];
                        // if($transctionid){
                        //$sql ="update T_UserChannelPayOrder set TransactionNo='".$transctionid."' where OrderId='".$order_id."'";
                        //     $db= new UserDB();
                        //     $state =  $db->TUserChannelPayOrder()->UPData(['TransactionNo'=>$transctionid], ['OrderId'=>$order_id]);
                        //}
                    }

                case 'easypay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $easypay = new EasySdk();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney
                    ];
                    $result = $easypay->pay($param,$detail);
                    $data['url'] = $result;
                    break;

                case 'goldpays':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $goldpay = new GoldSdk();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'AccountID' =>$UserID
                    ];
                    $result = $goldpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;

                case 'sepro':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $sepropay = new SeproPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'AccountID' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $sepropay->pay($param,$detail);
                    $data['url'] = $result;
                    break;

                case 'gdpaid':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $gdpay = new GDSdk();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $gdpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;

                case 'aupay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $aupay = new AuPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $aupay->pay($param,$detail);
                    $data['url'] = $result;
                    break;

                case 'doipay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $doipay = new DoiPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $doipay->pay($param,$detail);
                    $data['url'] = $result;
                    break;

                case 'beepay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $beepay = new BeePay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $beepay->pay($param,$detail);
                    $data['url'] = $result;
                    break;

                case 'payplus':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $payplus = new PayPlus();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $payplus->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'inpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $inpay = new InPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $inpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'serpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $inpay = new SerPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $inpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;

                case 'wepay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $wepay = new WePay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $wepay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'tgpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $tgpay = new TgPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $tgpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'dypay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $dypay = new DyPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $dypay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'hwepay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $hwepay = new Hwepay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $hwepay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'hwpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $hwpay = new Hwpay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $hwpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'joypay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $joypay = new Joypay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $joypay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'tpays':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $tpays = new Tpays();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $tpays->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'winpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $winpay = new Winpay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $winpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'ydpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $ydpay = new Ydpay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $ydpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'xjpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $xjpay = new Xjpay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $xjpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                default:
                    $class = '\\'.strtolower($channelcode).'\PaySdk';
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $pay = new $class();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $pay->pay($param,$detail);
                    $data['url'] = $result;
                    break;

            }

            if (empty($data["url"])) {
                $data['code'] = 101;
                $data['msg'] = 'The Payment Channel is Not available!';
            }
            return Utility::apiReturn($data['code'], json_encode($data), $data['msg']);
            //return json($data);
        // }
        // catch (Exception $ex){
        //     // log::INFO('充值错误：'.$ex->getMessage().$ex->getLine().$ex->getTraceAsString());
        //     save_log('exception','Exception:' . $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
        //     return Utility::apiReturn(111, '', 'request time out');
        // }
    }


    public function MakeOrderByDC() {
        
        // save_log('tgpay','qingqiu参数');
       try {
            $params = Utility::request(['channelID', 'roleid', 'amount','ordertype','active', 'time', 'sign']);
           save_log('allpay','所有支付请求参数'.json_encode($params));
            $UserID = $params["roleid"];
            $ChannelId = $params['channelID'];//111;
            $RealMoney = $params['amount'];//实际金额 对应表 masterdb.t_shopitem  RealMoney
            $time = $params['time'];
            $ordertype = $params['ordertype'];
            $date = date("YmdHis");
            // $date2 = time();
            $active = $params['active'] ?? 1;

            $db = new MasterDB();
            $channel = $db->getTableRow('T_GamePayChannel', ['channelID' => $ChannelId], '*');
            if (!$channel) {
                return Utility::apiReturn(101, '', 'the channel is not exists');
            }

            $rancode = sprintf('%04d',rand(0,9999));
            $rancode2 = rand(1000,9999);
            $order_id = 'TP'.$rancode2.$date.$rancode;
            $detailconfig =str_replace('/\s/','',$channel['MerchantDetail']);
            $detail =json_decode($channel['MerchantDetail'],true);

            if (intval($channel['MinMoney']) > $RealMoney) {
                return Utility::apiReturn(102, '', 'The payment amount is less than the minimum configuration amount of the channel.');
            }

            if (intval($channel['MaxMoney']) < $RealMoney) {
                return Utility::apiReturn(102, '', 'The payment amount is greater than the maximum configured amount of the channel.');
            }

            if (!$channel['Status']) {
                return Utility::apiReturn(103, '', 'The Payment channel closed.');
            }
            $channelcode = $channel['ChannelCode'];
            $save_data=[
                'ChannelID'=> $ChannelId,
                'OrderId' => $order_id,
                'PayType' => $ordertype,
                'AccountID'=> $UserID,
                'RealMoney' =>$RealMoney,
                'Active'  => $active,
                'Status' =>0,
                'AddTime' => date('Y-m-d H:i:s',time()),
                'PayTime' => date('Y-m-d H:i:s',time())
            ];
            $userdb = new UserDB();
            $info = $userdb->TUserChannelPayOrder()->Insert($save_data);
            if(!$info)
                return Utility::apiReturn(104, '', 'Failed to add order data.');

            switch ($channelcode) {
                case 'bananapay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $extra = json_encode(['channelid' =>$ChannelId]);
                    $ret = $this->bananaPay($order_id, $RealMoney, $detail,$extra);
                    if($ret){
                        $data['url'] = $ret['payurl'];
                        $transctionid = $ret['transactionId'];
                        if($transctionid){
                            //$sql ="update T_UserChannelPayOrder set TransactionNo='".$transctionid."' where OrderId='".$order_id."'";
                            $db= new UserDB();
                            $state =  $db->TUserChannelPayOrder()->UPData(['TransactionNo'=>$transctionid], ['OrderId'=>$order_id]);

                        }
                    }
                    break;

                case 'tpay9':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $paysdk = new PaySdk();
                    $param =[
                        'ip' => getClientIP(),
                        'roleid' => $UserID,
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney
                    ];
                    $result = $paysdk->pay($param,$detail);
                    if($result){
                        $data['url'] = $result['d']['h5'];
                    }
                    break;
                case 'fmpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';

                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney
                    ];
                    $fmpay = new FmPaySdk();
                    $result = $fmpay->pay($param,$detail);
                    if(stripos($result,'https://')>=0){
                        $data['url'] = $result;//$result['pay_url'];
                        //$transctionid = $result['trade_no'];
                        // if($transctionid){
                        //$sql ="update T_UserChannelPayOrder set TransactionNo='".$transctionid."' where OrderId='".$order_id."'";
                        //     $db= new UserDB();
                        //     $state =  $db->TUserChannelPayOrder()->UPData(['TransactionNo'=>$transctionid], ['OrderId'=>$order_id]);
                        //}
                    }

                case 'easypay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $easypay = new EasySdk();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney
                    ];
                    $result = $easypay->pay($param,$detail);
                    $data['url'] = $result;
                    break;

                case 'goldpays':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $goldpay = new GoldSdk();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'AccountID' =>$UserID
                    ];
                    $result = $goldpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;

                case 'sepro':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $sepropay = new SeproPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'AccountID' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $sepropay->pay($param,$detail);
                    $data['url'] = $result;
                    break;

                case 'gdpaid':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $gdpay = new GDSdk();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $gdpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;

                case 'aupay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $aupay = new AuPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $aupay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'doipay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $doipay = new DoiPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $doipay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'beepay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $beepay = new BeePay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $beepay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'payplus':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $payplus = new PayPlus();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $payplus->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'inpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $inpay = new InPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $inpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'serpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $inpay = new SerPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $inpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'wepay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $wepay = new WePay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $wepay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'tgpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $tgpay = new TgPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $tgpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'dypay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $dypay = new DyPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $dypay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'ssspay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $ssspay = new SSSPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $ssspay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'abcpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $abcpay = new ABCPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $abcpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'swiftpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $ssspay = new SwiftPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $ssspay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'epay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $epay = new EpayPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $epay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'icbcpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $abcpay = new ICBCPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $abcpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'fastpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $fastpay = new fastpay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $fastpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'xpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $xpay = new Xpay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $xpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'wodeasy':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $wodeasy = new WodEasyPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $wodeasy->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'hwepay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $hwepay = new Hwepay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $hwepay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'hwpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $hwpay = new Hwpay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $hwpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'joypay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $joypay = new Joypay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $joypay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'tpays':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $tpays = new Tpays();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $tpays->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'indipay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $indipay = new IndiPay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $indipay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'winpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $winpay = new Winpay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $winpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                 case 'ydpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $ydpay = new Ydpay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $ydpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'xjpay':
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $xjpay = new Xjpay();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $xjpay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
                case 'testpay':
                    return $this->payOrder();
                    break;
                default:
                    save_log('allpay','支付通道'.json_encode($channelcode));
                    $class = '\\'.strtolower($channelcode).'\PaySdk';
                    $data['code'] = 0;
                    $data['msg'] = 'success';
                    $pay = new $class();
                    $param =[
                        'orderid'=> $order_id,
                        'amount' =>$RealMoney,
                        'roleid' =>$UserID,
                        'paytime' =>$save_data['AddTime']
                    ];
                    $result = $pay->pay($param,$detail);
                    $data['url'] = $result;
                    break;
            }

            if (empty($data["url"])) {
                $data['code'] = 101;
                $data['msg'] = 'The Payment Channel is Not available!';
            }
            save_log('result','返回给客户端数据'.json_encode($data));
            return json($data);
        }
       catch (Exception $ex){
           // log::INFO('充值错误：'.$ex->getMessage().$ex->getLine().$ex->getTraceAsString());
           save_log('exception','Exception:' . $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
           return Utility::apiReturn(111, '', 'Api Error');
       }
    }


    //服务端推送信息，jai专用
    public function PushMsg(){
        $params = Utility::request(['roleid', 'type', 'FcmTitle','FcmMsg']);
        save_log('pushmsg','input:'.json_encode($params));
        if(!isset($params['roleid'])){
            return Utility::apiReturn(100, '', 'roleid is empty!');
        }

        //0:pc 1:android 2:ios 3:wp
        $accountdb =new AccountDB();
        $userdevicetoken = $accountdb->getTableRow('T_DeviceToken', ['AccountID' => $params['roleid'],'DeviceType'=>1], '*');
        if(!isset($userdevicetoken['DeviceToken'])){
            return Utility::apiReturn(110, '', 'DeviceToken is empty!');
        }

        $sign =md5('rummyjai3b5af0f0fe7c68ea06d4876d746e219e');
        $pushmsg=[
            'operator_id'=>'rummyjai',
            'device'=>1,
            'token' => $userdevicetoken['DeviceToken'],
            'title'=>$params['FcmTitle'],
            'message'=>$params['FcmMsg'],
            'sign' =>$sign
        ];
        $url='http://18.140.253.72/api/index/push';
        $post_json =json_encode($pushmsg);
        $header = [
            'Content-Type: application/json;charset=utf-8',
        ];
        $resp =urlhttpRequest($url,$post_json,$header);
        save_log('pushmsg','input:'.$post_json.',output:'.$resp);
        if($resp){
            if(isset($resp['status']))
                if($resp['status'] && $resp['data'])
                    return Utility::apiReturn(0, '', 'push success!');
        }
        return Utility::apiReturn(120, '', 'the gateway return error!');
    }






}