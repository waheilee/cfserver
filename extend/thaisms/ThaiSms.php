<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/2
 * Time: 15:23
 */

namespace thaisms;

use redis\Redis;
use socket\sendQuery;
use Utility\Utility;
use think\facade\Cache;
class ThaiSms
{
    protected $api_url= '';
    protected $username= '';
    protected $password= '';

    public function __construct() {
        $this->api_url= 'http://www.thsms.com/api/rest';
        $this->username= 'thafishing';
        $this->password=  '44ba5a';
    }

    public  function SendSms($mobile,$type)
    {
        if (empty($mobile)) Utility::response(-100, '手机号码为空');
        $code =[];
        if($type=='100'){
            $code = $this->ASMakePhoneSeccode($mobile);
        }
        else if($type=='200'){
            $code = $this->getResetPwdCode($mobile);
        }

        if (empty($code['iResult'])) {
            Utility::response(-100, '验证码获取失败');
        } else {
            $smsCode = $code['iResult'];
            if (strlen($smsCode) == 4) {
                $msg = "";
                $send_mobile = substr($mobile,2);
                $content = "【Teen Patti Rummy】verifyCode:{$smsCode}";
                $status =  $this->send('OTP', $send_mobile, $content, $msg);
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
                    Utility::response(0, '验证码获取成功，请查收');
                } else {
                    Utility::response(-200, '验证码获取失败，请重试');
                }
            } else {
                Utility::response(-300, '获取失败，请重试');
            }
        }
    }


    private function ASMakePhoneSeccode($mobile)
    {
        $sendQuery = new sendQuery();
        //调用 sendQuery  CMD_MD_PHONE_SECCODE
        $socket = $sendQuery->callback("CMD_MD_PHONE_SECCODE", [$mobile], 'DC');
        if (empty($socket)) return 0;
        $out_array = unpack('LiResult/', $socket);//   ProcessAWOperateAckRes($out_data);
        return $out_array;
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


    public function getCredit()
    {
        $params['method'] = 'credit';
        $params['username'] = $this->username;
        $params['password'] = $this->password;
        $result = $this->curl($params);
        $xml = @simplexml_load_string($result);
        if (!is_object($xml)) {
            return array(FALSE, 'Respond error');
        } else {
            if ($xml->credit->status == 'success') {
                return array(TRUE, $xml->credit->amount);
            } else {
                return array(FALSE, $xml->credit->message);
            }
        }
    }

    private function send($from = '0000', $to = null, $message = null)
    {
        $params['method'] = 'send';
        $params['username'] = $this->username;
        $params['password'] = $this->password;
        $params['from'] = $from;
        $params['to'] = $to;
        $params['message'] = $message;
        if (is_null($params['to']) || is_null($params['message'])) {
            return FALSE;
        }
        $result = $this->curl($params);
        save_log('sms',$result);
        $xml = @simplexml_load_string($result);
        if (!is_object($xml)) {
            return false;//array(FALSE, 'Respond error');
        } else {
            if ($xml->send->status == 'success') {
                return true;//array(TRUE, $xml->send->uuid);
            } else {
                return false;//array(FALSE, $xml->send->message);
            }
        }
    }

    private function curl($params = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $resp = curl_exec($ch);
        $lastError = curl_error($ch);
        $lastReq = curl_getinfo($ch);
        curl_close($ch);
        return $resp;
    }
}