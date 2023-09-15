<?php

namespace sdk\SMS_SDK;

use sdk\SMS_SDK\indiahmSDK;
use socket\sendQuery;
use think\facade\Log;
use think\response\Json;
use Utility\Utility;


class SMSinit
{

    public function INITindiaSMS($mobile) {
        if (empty($mobile)) Utility::response(-100, '手机号码为空');
        $code = $this->ASMakePhoneSeccode($mobile);
        if (empty($code['iResult'])) {
            Utility::response(-100, '验证码获取失败');
        } else {
            $smsCode = $code['iResult'];
            if (strlen($smsCode) == 4) {
                $msg = "";
                $content = "{Teen Patti Rummy}verifyCode {$smsCode} is your verification OTP -XPROSP";
                $code = self::indiaSMS($mobile, $content, $msg);
                if ($code) {
                    Utility::response(0, '验证码获取成功，请查收',json_decode($msg));
                } else {
                    Utility::response(-100, '验证码获取失败，请重试', json_decode($msg));
                }
            } else {
                Utility::response(-100, '获取失败，请重试');
            }
        }

    }

    /**
     * 创蓝国际 短信接口
     * @param $countrycode
     * @param $mobile
     * @param $content
     * @param $ApiMessage
     * @return bool
     */
    protected static function CLsendSms($countrycode, $mobile, $content, &$ApiMessage) {
        $clApi = new ChuanglanSDK();
        $result = '';
        switch ($countrycode) {
            case '0091':
                $result = $clApi->sendSMS($countrycode . $mobile, $content);
                break;
            case '0086':
                $result = $clApi->sendInnerSMS($mobile, $content, 'true');
                break;
            default:
                $apimsg = ['errorMsg' => '未定义国家代码不予发送'];
        }
        $ApiMessage = $result;
        if (!is_null(json_decode($result))) {
            $output = json_decode($result, true);
            if (isset($output['code']) && $output['code'] == '0') {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    /**
     * 联动世纪
     * @param string $countrycode 国家代码
     * @param string $mobile 手机号码
     * @param string $content 短信内容
     * @param Json $ApiMessage 接口回调消息
     * @return bool
     */
    protected static function indiaSMS($mobile, $content, &$ApiMessage) {
        $indiahmSDK = new indiahmSDK();
        $result = '';
        $countrycode = substr($mobile, 2,2);//国家代码 4位
        $mobile=substr($mobile, 4); //纯手机号
        switch ($countrycode) {
            case '91':
                $result = $indiahmSDK->sendSMS($countrycode . $mobile, $content);
                break;
//            case '0086':
//                $result = $indiahmSDK->sendSMS($mobile, $content);
//                break;
            default:
                $apimsg = ['errorMsg' => '未定义国家代码不予发送'];
        }
        Log::init(['path' => APP_PATH . '../logs/SMS/']);
        Log::INFO("$countrycode $mobile, $content");
        Log::INFO(json_decode($result));
        $ApiMessage = $result;
        if (!is_null(json_decode($result))) {
            $output = json_decode($result, true);
            if (isset($output['status']) && $output["smslist"][0]["result"] !=3 ) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    function ASMakePhoneSeccode($mobile) {
        $sendQuery = new sendQuery();
        //调用 sendQuery  CMD_MD_PHONE_SECCODE
        $socket = $sendQuery->callback("CMD_MD_PHONE_SECCODE", [$mobile], 'DC');
        if (empty($socket)) return 0;
        $out_array = unpack('LiResult/', $socket);//   ProcessAWOperateAckRes($out_data);
        return $out_array;
    }


}