<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/11/29
 * Time: 15:09
 */

namespace mail;

use app\model\GameOC;
use redis\Redis;
use socket\sendQuery;
use Utility\Utility;
use think\facade\Cache;
use PHPMailer\PHPMailer\PHPMailer;

class MailSdk
{

    public function SendLoginMail($mail,$config,$type)
    {
        if (empty($mail)) Utility::response(-100, 'Please input email.');
        $code = [];

        if($type=='100'){
            $code = $this->ASMakePhoneLoginSeccode($mail);
        }
        else if($type=='200'){
            $code = $this->getResetPwdCode($mail);
        }

        if (empty($code['iResult'])) {
            Utility::response(-100, 'The verification code acquisition failed, please try again');
        } else {
            $smsCode = $code['iResult'];
            $GameOC = new GameOC();
            $GameOC->SmsCodeLog()->insert(['code' => $smsCode, 'mobile' => $mail]);
            save_log('mail', $mail . '游服返回验证码:' . $smsCode);
            $status = $this->sendmail($mail,$smsCode,$config);
            if ($status) {
                Cache::set($mail, $mail, 120);
                $dayily = Redis::get($mail);
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
                Redis::set($mail, $data, 24 * 60 * 60);
                Utility::response(1, 'The verification code obtained successfully, please check ');
            } else {
                Utility::response(-200, 'The verification code acquisition failed, please try again');
            }
        }
    }


    //发送绑定邮箱
    public function SendBindMail($mail,$config,$type)
    {
        if (empty($mail)) Utility::response(-100, 'Please input email.');
        $code = [];

        if($type=='100'){
            $code = $this->ASMakePhoneSeccode($mail);
        }
        else if($type=='200'){
            $code = $this->getResetPwdCode($mail);
        }

        if (empty($code['iResult'])) {
            Utility::response(-100, 'The verification code acquisition failed, please try again');
        } else {
            $smsCode = $code['iResult'];
            $GameOC = new GameOC();
            $GameOC->SmsCodeLog()->insert(['code' => $smsCode, 'mobile' => $mail]);
            save_log('mail', $mail . '游服返回验证码:' . $smsCode);
            $status = $this->sendmail($mail,$smsCode,$config);
            if ($status) {
                Cache::set($mail, $mail, 120);
                $dayily = Redis::get($mail);
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
                Redis::set($mail, $data, 24 * 60 * 60);
                Utility::response(1, 'The verification code obtained successfully, please check ');
            } else {
                Utility::response(-200, 'The verification code acquisition failed, please try again');
            }
        }
    }



    public  function  sendmail($email,$checkcode,$config){
        $mail =new PHPMailer();
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        // smtp需要鉴权 这个必须是true
        $mail->SMTPAuth = true;
        // 链接qq域名邮箱的服务器地址
        $mail->Host = $config['Host'];
        // 设置使用ssl加密方式登录鉴权
        $mail->SMTPSecure =$config['ssl'];
        // 设置ssl连接smtp服务器的远程服务器端口号
        $mail->Port = $config['Port'];
        // 设置发送的邮件的编码
        $mail->CharSet = 'UTF-8';
        // 设置发件人昵称 显示在收件人邮件的发件人邮箱地址前的发件人姓名
        $mail->FromName = $config['FromName'];
        // smtp登录的账号 QQ邮箱即可
        $mail->Username = $config['Username'];
        // smtp登录的密码 使用生成的授权码
        $mail->Password = $config['Password'];
        // 设置发件人邮箱地址 同登录账号
        $mail->From = $config['Username'];
        // 邮件正文是否为html编码 注意此处是一个方法
        $mail->isHTML(true);
        // 设置收件人邮箱地址
        $mail->addAddress($email);
        // 添加多个收件人 则多次调用方法即可
        //$mail->addAddress('87654321@163.com');
        // 添加该邮件的主题
        $mail->Subject = $config['Subject'];
        $content = str_replace('{code}',$checkcode,$config['Body']);
        // 添加邮件正文
        $mail->Body = $content;
        // 为该邮件添加附件
        //$mail->addAttachment('./example.pdf');
        // 发送邮件 返回状态
        $status = $mail->send();
        return $status;
    }


    private function ASMakePhoneSeccode($mobile)
    {
        $sendQuery = new sendQuery();
        //调用 sendQuery  CMD_MD_PHONE_SECCODE
        $appname = config('appname');
        if(empty($appname)|| $appname=='tp'){
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

}