<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/5
 * Time: 22:12
 */

namespace indipay;

use indipay\Rsa;
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
            $this->merchant = $config['merchant'];
            $this->secret = $config['secret'];
        }

        $paramter=[
            'payAmount'=>sprintf('%.2f',$param['amount']),
            'commercialOrderNo'=> strval($param['orderid']),
            'callBackUrl'=>$config['notify_url'],
            'notifyUrl'=>$config['notify_url'],
            'userId' =>strval($param['roleid'])
        ];

        $json_para = json_encode($paramter);
        $dec_para =$this->encrypt($json_para,$this->secret);
        $requestarray = array(
            'platformno' => strval($this->merchant),
            'parameter' =>$dec_para,
            'payType' =>$config['paytype']
        );
        $md5keysignstr = md5($json_para);
        $requestarray['sign'] =$md5keysignstr;
        $requestHttpDate = http_build_query($requestarray);                     //转换为URL键值对（即key1=value1&key2=value2…）
        $header = [
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
        ];
        $curl_result = $this->httpRequestDataTest($this->api_url . '/api/pay/apply', $requestHttpDate,$header);    //发送http的post请求
        $res = json_decode($curl_result, true);
        $returl = '';
        if ($res['result'] == 'success') {
            if (!empty($res['payUrl'])) {
                $returl = $res['payUrl'];
            }
        }
        return $returl;
    }

    private function encrypt($message, $key,$methon='AES-256-ECB')
    {
        if (mb_strlen($key, '8bit') !== 32) {
            throw new Exception("Needs a 256-bit key!");
        }
        $ivsize = openssl_cipher_iv_length($methon);
        $iv     = openssl_random_pseudo_bytes($ivsize);
        $ciphertext = openssl_encrypt(
            $message,
            $methon,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        return base64_encode($iv . $ciphertext);
    }

    private function decrypt($message, $key,$methon='AES-256-ECB')
    {
        if (mb_strlen($key, '8bit') !== 32) {
            throw new Exception("Needs a 256-bit key!");
        }
        $message    = base64_decode($message);
        $ivsize     = openssl_cipher_iv_length($methon);
        $iv         = mb_substr($message, 0, $ivsize, '8bit');
        $ciphertext = mb_substr($message, $ivsize, null, '8bit');
        return openssl_decrypt(
            $ciphertext,
            $methon,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
    }

    //http请求函数
    private function httpRequestDataTest($url, $data = '', $headers = array(), $method = 'POST', $timeOut = 30, $agent = '')
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
        save_log('indipay', 'url:'.$url.',data==='.json_encode($data));
        save_log('indipay','output:' . $file_contents);
        //这里解析
        return $file_contents;
    }
}