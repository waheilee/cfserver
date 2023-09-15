<?php

namespace sdk\SMS_SDK;
/* *
 * 类名：ChuanglanSMS
 * 功能：创蓝接口请求类
 * 详细：构造创蓝短信接口请求，获取远程HTTP数据
 * 版本：1.3
 * 日期：2021-03-29
 */
class ChuanglanSDK
{

    /// 国际
    /**
     *创蓝国际 API账号
     */
    const API_ACCOUNT= 'I4461207'; //
    /**
     *创蓝国际 API密码
     */
    const API_PASSWORD= 'wLe9KmCp7sf577';// 创蓝API密码
    //参数的配置 请登录zz.253.com 获取以上API信息 ↑↑↑↑↑↑↑

    /// 国内
    /**
     *  创蓝国内 API账号
     */
    const API_INNER_ACCOUNT= 'N2252263';
    /**
     *创蓝国内 API密码
     */
    const API_INNER_PASSWORD= 'DAuoW4O8xGc740';//


    //参数的配置 请登录zz.253.com 获取以下API信息 ↓↓↓↓↓↓↓
    /**
     * 创蓝国际 短信接口URL
     */
    const API_SEND_URL='http://intapi.253.com/send/json';
    /**
     *创蓝国内 短信接口URL
     */
    const API_INNER_URL ='http://smssh1.253.com/msg/send/json';
    /**
     *创蓝变量短信接口URL
     */
    const API_VARIABLE_URL = 'http://intapi.253.com/msg/variable/json';
    /**
     *创蓝短信余额查询接口URL
     */
    const API_BALANCE_QUERY_URL= 'http://smssh1.253.com/msg/balance/json';


    /**
     * 发送短信 创蓝 国际接口
     *
     * @param string $mobile 手机号码
     * @param string $msg 短信内容
     * @param string $needstatus 是否需要状态报告
     * @return mixed|string
     */
    public function sendSMS($mobile, $msg, $needstatus = 'true')
    {
        //创蓝接口参数
        $postArr = array(
            'account' => self::API_ACCOUNT,
            'password' => self::API_PASSWORD,
            'msg' => $msg,
            'mobile' => $mobile,
            'report' => $needstatus,
        );
        //file_put_contents('./data.txt',json_encode($postArr));
        save_log('sms',json_encode($postArr));
        $result = $this->curlPost(self::API_SEND_URL, $postArr);
        save_log('sms',json_encode($result));
        return $result;
    }


    /**
     * 发送短信 创蓝 国内接口
     *
     * @param string $mobile 手机号码
     * @param string $msg 短信内容
     * @param string $needstatus 是否需要状态报告
     * @return mixed|string
     */
    public function sendInnerSMS($mobile, $msg, $needstatus = 'true')
    {
        $postArr = array(
            'account' => self::API_INNER_ACCOUNT,
            'password' => self::API_INNER_PASSWORD,
            'msg' => $msg,
            'phone' => $mobile,
            'report' => $needstatus,
        );
        //file_put_contents('./data.txt',json_encode($postArr));
        $result = $this->curlPost(self::API_INNER_URL, $postArr);
        return $result;
    }

    /**
     * 发送变量短信
     *
     * @param string $msg 短信内容
     * @param string $params 最多不能超过1000个参数组
     */
    public function sendVariableSMS($msg, $params)
    {

        //创蓝接口参数
        $postArr = array(
            'account' => self::API_ACCOUNT,
            'password' => self::API_PASSWORD,
            'msg' => $msg,
            'params' => $params,
            'report' => 'true'
        );

        $result = $this->curlPost(self::API_VARIABLE_URL, $postArr);
        return $result;
    }

    /**
     * 查询额度
     *
     *  查询地址
     */
    public function queryBalance()
    {
        //查询参数
        $postArr = array(
            'account' => self::API_ACCOUNT,
            'password' => self::API_PASSWORD,
        );
        $result["data"] = $this->curlPost(self::API_BALANCE_QUERY_URL, $postArr);
//        $result["apiMessage"]=$apiMessage;
        return $result;
    }

    /**
     * 通过CURL发送HTTP请求
     * @param string $url //请求URL
     * @param array $postFields //请求参数
     * @return mixed
     *
     */
    private function curlPost($url, $postFields)
    {
        $postFields = json_encode($postFields);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            ['Content-Type: application/json; charset=utf-8']    //json版本需要填写  Content-Type: application/json;

        );
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); //若果报错 name lookup timed out 报错时添加这一行代码
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $ret = curl_exec($ch);
        if (false == $ret) {
            $result = curl_error($ch);
        } else {
            $rsp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 != $rsp) {
                $result = "请求状态 " . $rsp . " " . curl_error($ch);
            } else {
                $result = $ret;
            }
        }
        curl_close($ch);
        return $result;
    }

}