<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/11/29
 * Time: 15:09
 */
namespace paasoo;
use app\model\GameOC;
use redis\Redis;
use socket\sendQuery;
use Utility\Utility;
use think\facade\Cache;

class SmsSdk
{

    protected $api_url= 'https://api.paasoo.com/json';

    //king
//    protected $username= '2a1bejws';
//    protected $password= '7pzi1tyn';


//rate
    protected $username= '';
    protected $password= '';
    public function __construct() {
        $this->api_url= 'https://api.paasoo.com/json';
//        $this->username= '2a1bejws';
//        $this->password=  '7pzi1tyn';

        $this->username= 'sspzwj9g';
        $this->password=  'MHhhUAHu';
    }

    public  function SendSms($mobile,$type)
    {
        if (empty($mobile)) Utility::response(-100, 'The Mobile phone number cannot be empty.');
        $code =[];
        if($type=='100'){
            $code = $this->ASMakePhoneSeccode($mobile);
        }
        else if($type=='200'){
            $code = $this->getResetPwdCode($mobile);
        }

        if (empty($code['iResult'])) {
            Utility::response(-100, 'Verification code acquisition failed, please try again.');
        } else {
            $smsCode = $code['iResult'];
            $GameOC = new GameOC();
            $GameOC->SmsCodeLog()->insert(['code'=>$smsCode,'mobile'=>$mobile]);
            if (strlen($smsCode) == 4) {
                $msg = "";
                //$send_mobile = substr($mobile,2);
//                $content = "OTP: {$smsCode} for your phone verification. INDLAZA";//"Your verification code is {$smsCode}, do not disclose it to anyone else.KREDIQA";
//                $status =  $this->send('NDLAZA', $mobile, $content);
                $content = "[RummyW] {$smsCode} is your OTP.  This code will expire in 10 minutes. Please do not disclose it for security purpose.";
                $status =  $this->send('RummyW', $mobile, $content);

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
                    Utility::response(0, 'Verification code obtained successfully, please check.');
                } else {
                    Utility::response(-200, 'Verification code acquisition failed, please try again.');
                }
            } else {
                Utility::response(-300, 'Verification code acquisition failed, please try again.');
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




    private function send($from = '0000', $to = null, $message = null)
    {

        $param = '?key='.$this->username.'&secret='.$this->password.'&from='.$from.'&to='.$to.'&text='.urlencode($message);
        $url = $this->api_url.$param;
        $result = $this->curl_get_https($url);
        save_log('indiasms',$url.'|'.$result);
        if(!empty($result))
        {
            $data = json_decode($result,true);
            return $data['status']==0;
        }
        return 0;
    }

    private function  curl_get_https($url){
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);  // 从证书中检查SSL加密算法是否存在
        $tmpInfo = curl_exec($curl);     //返回api的json对象
        //关闭URL请求
        curl_close($curl);
        return $tmpInfo;    //返回json对象
    }



}