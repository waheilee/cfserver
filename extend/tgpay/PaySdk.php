<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace tgpay;

use Utility\Utility;
use think\facade\Cache;

class PaySdk
{

    private $api_url = '';
    private $notify_url = '';
    private $appid = '';
    private $secret = '';


    public function __construct()
    {
        $this->api_url = '';
        $this->merchant = '';
        $this->secretkey = '';
    }


    public function pay($param, $config = [])
    {
        if ($config) {
            $this->api_url = $config['apiurl'];
            $this->appid = $config['appid'];
            $this->secret = $config['secret'];
        }
        $requestarray = array(
            'pay_memberid' => $this->appid,                             //填写你的商户号
            'pay_orderid' => $param['orderid'],                         //商户订单
            'pay_userid' => $param['roleid'],                           //商户应用内发起支付的用户的userid，即商户那边的userid
            'pay_applydate' => $param['paytime'],                       //请求时间
            'pay_bankcode' => $config['code'],                                    //请求的支付通道ID
            'pay_notifyurl' => trim($config['notify_url']),                 //支付通知地址		(填写你的支付通知地址)
            'pay_callbackurl' => $config['notify_url'],                 //同步前端跳转地址	(填写你需要的前端跳转地址)
            'pay_amount' => strval($param['amount'])
        );

        $md5keysignstr = $this->createSign($this->secret, $requestarray);       //生成签名
        $requestarray['pay_md5sign'] = $md5keysignstr;                          //签名后的md5码
        $requestarray['pay_productname'] = 'ID' . $param['roleid'];          //支付订单的名字
        $requestHttpDate = http_build_query($requestarray);                     //转换为URL键值对（即key1=value1&key2=value2…）
        $header = [
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
        ];

        $curl_result = $this->httpRequestDataTest($this->api_url . '/Pay_Index.html', $requestHttpDate,$header);    //发送http的post请求

        $res = json_decode($curl_result, true);
        
//        if (!empty($res['status'])) {
//            $res['message'] = 'Http Request Invalid';
//            //exit('Http Request Invalid');
//        }

        $returl = '';
        if ($res['status'] == 'success') {
            if (!empty($res['data']['payurl'])) {
                $returl = $res['data']['payurl'];
            }
        }
        return $returl;
    }


    //签名函数
    protected function createSign($Md5key, $list)
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
    

    //http请求函数
    public function httpRequestDataTest($url, $data = '', $headers = array(), $method = 'POST', $timeOut = 30, $agent = '')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeOut);           //请求超时时间
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeOut);    //链接超时时间
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        // https请求 不验证证书和hosts
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            if ($data != '') curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $file_contents = curl_exec($ch);
        curl_close($ch);
        //$httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        save_log('tgpay', 'url:'.$url.',data==='.json_encode($data));
        save_log('tgpay','output:' . $file_contents);
        //这里解析
        return $file_contents;
    }
}