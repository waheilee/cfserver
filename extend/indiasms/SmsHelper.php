<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/2
 * Time: 15:23
 */

namespace indiasms;

use redis\Redis;
use socket\sendQuery;
use Utility\Utility;
use app\model\GameOC;
use think\facade\Cache;
class SmsHelper
{

    //appkey：shi168
    //appsecret：46aYor
    //appcode：1000
    //http://47.242.85.7:9090/sms/batch/v2?appkey=QFPay1&appsecret=abc123&phone=91988656453&msg=%E6%B5%8B%E8%AF%95%E7%9F%AD%E4%BF%A1&appcode=1000

    public  function SendSms($mobile,$type)
    {
        if (empty($mobile)) Utility::response(-100, 'Please enter your mobile number');
        $code =[];
        if($type=='100'){
            $code = $this->ASMakePhoneSeccode($mobile);
        }
        else if($type=='200'){
            $code = $this->getResetPwdCode($mobile);
        }

        if (empty($code['iResult'])) {
            Utility::response(-100, 'Then OPT code acquisition failed');
        } else {
            $smsCode = $code['iResult'];
            $GameOC = new GameOC();
            $GameOC->SmsCodeLog()->insert(['code'=>$smsCode,'mobile'=>$mobile]);
            save_log('sms',$mobile.'游服返回验证码:' . $smsCode);
            if (strlen($smsCode) == 4) {
                //$send_mobile = substr($mobile,2);
                $content = "Dear Customer, Your otp is {$smsCode}. Thank You!";
                $status =  $this->send($mobile, $content);
                if ($status) {
                    Cache::set($mobile,$mobile,120);
                    $dayily = Redis::get($mobile);
                    $times =1;
                    $date =date('Y-m-d',time());
                    if($dayily){
                        if($dayily['date']==$date){
                            $times = $dayily['times']+1;
                        }
                    }
                    $data =[
                        'date'=>$date,
                        'times' =>$times
                    ];
                    Redis::set($mobile,$data,24*60*60);
                    Utility::response(1, 'The verification code obtained successfully, please check ');
                } else {
                    Utility::response(-200, 'The verification code acquisition failed, please try again');
                }
            } else {
                Utility::response(-300, 'The verification code acquisition failed, please try again');
            }
        }
    }


    public  function SendLoginSms($mobile,$type)
    {
        if (empty($mobile)) Utility::response(-100, 'Please enter your mobile number');
        $code =[];
        if($type=='100'){
            $code = $this->ASMakePhoneLoginSeccode($mobile);
        }
        else if($type=='200'){
            $code = $this->getResetPwdCode($mobile);
        }

        if (empty($code['iResult'])) {
            Utility::response(-100, 'Then OPT code acquisition failed');
        } else {
            $smsCode = $code['iResult'];
            $GameOC = new GameOC();
            $GameOC->SmsCodeLog()->insert(['code'=>$smsCode,'mobile'=>$mobile]);
            save_log('loginsms',$mobile.'游服返回验证码:' . $smsCode);
            if (strlen($smsCode) == 4) {
                //$send_mobile = substr($mobile,2);
                $content = "Dear Customer, Your otp is {$smsCode}. Thank You!";
                $status =  $this->send($mobile, $content);
                if ($status) {
                    Cache::set($mobile,$mobile,120);
                    $dayily = Redis::get($mobile);
                    $times =1;
                    $date =date('Y-m-d',time());
                    if($dayily){
                        if($dayily['date']==$date){
                            $times = $dayily['times']+1;
                        }
                    }
                    $data =[
                        'date'=>$date,
                        'times' =>$times
                    ];
                    Redis::set($mobile,$data,24*60*60);
                    Utility::response(1, 'The verification code obtained successfully, please check ');
                } else {
                    Utility::response(-200, 'The verification code acquisition failed, please try again');
                }
            } else {
                Utility::response(-300, 'The verification code acquisition failed, please try again');
            }
        }
    }


    private function ASMakePhoneSeccode($mobile)
    {
        $sendQuery = new sendQuery();
        //调用 sendQuery  CMD_MD_PHONE_SECCODE
        $appname = config('appname');
        if( empty($appname) || $appname=='tp'){
            $socket = $sendQuery->callback("CMD_MD_PHONE_SECCODE", [$mobile], 'DC');
        }
        else{
            $socket = $sendQuery->callback("CMD_MD_PHONE_SECCODE_FiVE", [$mobile], 'DC');
        }

        if (empty($socket)) return 0;
        $out_array = unpack('LiResult/', $socket);//   ProcessAWOperateAckRes($out_data);
        return $out_array;
    }


    private function ASMakePhoneLoginSeccode($mobile)
    {
        $sendQuery = new sendQuery();
        //调用 sendQuery  CMD_MD_PHONE_SECCODE
        $socket=$sendQuery->callback("CMD_WA_MAKE_PHONE_SECCODE", [$mobile], 'AS');
        if (empty($socket)) return 0;
        $outdata = $sendQuery->callback("CMD_WA_GET_PHONE_SECCODE", [$mobile], 'AS');
        if (empty($outdata)) return 0;
        $ret = $sendQuery->ProcessAWGetSeccodeRes($outdata);
        $code =0;
        if (is_array($ret['CodeInfoList'])) {
            $code = $ret['CodeInfoList'][0]['iCode'];
        }
        return ['iResult'=>$code];
    }



    private function getResetPwdCode($mobile)
    {
        $sendQuery = new sendQuery();
        //调用 sendQuery  CMD_MD_PHONE_SECCODE
        $socket = $sendQuery->callback("CMD_MD_RESET_SECCODE", [$mobile], 'AS');
        if (empty($socket)) return 0;
        $out_array = unpack('LiResult/', $socket);//   ProcessAWOperateAckRes($out_data);
        return $out_array;
    }




    private function send($mobile, $message)
    {
        $smsconfig = config('smsuser');
        $global = $smsconfig['channel'];
        switch ($global)
        {
            case 'pds':
                return $this->bazilchannel_pds($mobile, $message);
                break;

            case 'india':
                return $this->bazilchannel_india($mobile, $message);
                break;
        }
    }



    private  function bazilchannel_india($mobile, $message){
        $smsconfig = config('smsuser');
        $appkey=$smsconfig['appkey'];
        $appsecret=$smsconfig['appsecret'];
        $message = urlencode($message);
        $apiurl = $smsconfig['apiurl'];
        $url=$apiurl.'?appkey='.$appkey.'&appsecret='.$appsecret.'&phone='.$mobile.'&msg='.$message.'&appcode=1000';
        $resp = $this->curl($url);
        save_log('sms',$url.'============='.$mobile.'返回数据:' . $resp);
        $data = json_decode($resp,true);
        if(!empty($data)){
            if(isset($data['result'][0]['status'])){
                if($data['result'][0]['status']=='00000')
                    return true;
            }
        }
        return false;

    }



    private  function bazilchannel_pds($mobile, $message){
        $smsconfig = config('smsuser');
        $appkey=$smsconfig['appkey'];
        $appsecret=$smsconfig['appsecret'];
        $message = urlencode($message);
        $apiurl = $smsconfig['apiurl'];
        $timestamp =time();

        $sign =md5($appkey.$appsecret.$timestamp);
//        $post_data=[
//            'applyKey'=>$appkey,
//            'sign'=>$sign,
//            'phones'=>$mobile,
//            'context'=>$message,
//            'timestamp'=>$timestamp
//        ];

        $header = [
            'Content-Type: application/json;charset=utf-8',
        ];
        //Dear Customer, Your otp is {$smsCode}. Thank You!
        $jsonstr= '{"applyKey":"'.$appkey.'","sign":"'.$sign.'","phones":"'.$mobile.'","context":"'.$message.'","timestamp":'.$timestamp.'}';
        $jsonstr = str_replace("+", " ", $jsonstr);
        $jsonstr = str_replace("%2C", ".", $jsonstr);
        $jsonstr = str_replace("%21", "!", $jsonstr);
        $resp = urlhttpRequest($apiurl,$jsonstr,$header);
        save_log('sms','data============='.$jsonstr.'返回数据:' . $resp);
        $data = json_decode($resp,true);
        if(!empty($data)){
            if(isset($data['code'])){
                if($data['code']==200)
                    return true;
            }
        }
        return false;
    }



    private function curl($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $resp = curl_exec($ch);
        $lastError = curl_error($ch);
        $lastReq = curl_getinfo($ch);
        curl_close($ch);
        return $resp;
    }
}