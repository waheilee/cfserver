<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace fastpay;

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
		$amount = sprintf('%.2f',$param['amount']);
        $requestarray = array(
            'merchantNo' => $this->appid,                             //填写你的商户号
            'orderNo' => $param['orderid'],
            'amount' => floatval($amount),
            'type' =>$config['code'],
            'notifyUrl' => $config['notify_url'],
            'userName' => 'paul',
            'ext'=> $param['roleid'],
            'version' => '2.0.2'
        );

        $md5keysignstr = $this->createSign($this->secret, $requestarray);       //生成签名
        $requestarray['sign'] = $md5keysignstr;                          //签名后的md5码
        $requestHttpDate = json_encode($requestarray);                     //转换为URL键值对（即key1=value1&key2=value2…）
        $header = [
            'Content-Type: application/json;charset=utf-8',
        ];
        $curl_result = $this->httpRequestDataTest($this->api_url . '/okex-admin/okex/api/v2/pay', $requestHttpDate,$header);    //发送http的post请求
        $res = json_decode($curl_result, true);
        $returl = '';
        if ($res['code'] == 0) {
			$retsign = $res['sign'];			
			unset($res['sign']);
			$compare_sign = $this->createSign($this->secret,$res);		
            if ($compare_sign==$retsign) {
                $returl = $res['url'];
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
            if ($val!=='') {
                $tempstr = $tempstr . $key . "=" . $val . "&";
            }
        }
		$signstr=$tempstr . "key=" . $Md5key;		
        $md5str = md5($signstr); 	//最后拼接上key=ApiKey(你的商户秘钥),进行md5加密
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
        save_log('fastpay', 'url:'.$url.',data==='.json_encode($data));
        save_log('fastpay','output:' . $file_contents);
        //这里解析
        return $file_contents;
    }
}