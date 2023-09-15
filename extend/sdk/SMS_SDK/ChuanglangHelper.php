<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/9/26
 * Time: 15:21
 */

namespace sdk\SMS_SDK;
use app\model\GameOC;
use redis\Redis;
use socket\sendQuery;
use Utility\Utility;
use think\facade\Cache;

class ChuanglangHelper
{

    public function SendSms($mobile, $type)
    {
        if (empty($mobile)) Utility::response(-100, 'The Mobile phone number is empty.');
        $code = [];
        if ($type == '100') {
            $code = $this->ASMakePhoneSeccode($mobile);
        } else if ($type == '200') {
            $code = $this->getResetPwdCode($mobile);
        }

        if (empty($code['iResult'])) {
            Utility::response(-100, 'Verification code acquisition failed, please try again.');
        } else {
            $smsCode = $code['iResult'];
            $GameOC = new GameOC();
            $GameOC->SmsCodeLog()->insert(['code'=>$smsCode,'mobile'=>$mobile]);
            if (strlen($smsCode) == 4) {
                $content ="[Teen Patti Rummy] Your OTP for mobile verification is {$smsCode} This OTP is valid for 15 minutes.";
                //$content = "【Teen Patti Rummy】your verifyCode is:{$smsCode}";
                $sdk = new ChuanglanSDK();
                $retstr = $sdk->sendSMS($mobile, $content);
                $status = 0;
                if ($retstr) {
                    $retcode = json_decode($retstr, true);
                    if ($retcode['code'] == "0") {
                        $status = 1;
                    }
                }

                if ($status) {
                    Cache::set($mobile, $mobile, 120);
                    $dayily = Redis::get($mobile);
                    $times = 1;
                    $date = date('Y-m-d', time());
                    if ($dayily) {
                        if ($dayily['date'] == $date) {
                            $times = $dayily['times'] + 1;
                        }
                    }
                    $data = [
                        'date' => $date,
                        'times' => $times
                    ];
                    Redis::set($mobile, $data, 24 * 60 * 60);
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


}