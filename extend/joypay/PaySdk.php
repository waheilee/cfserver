<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace joypay;

use Utility\Utility;
use think\facade\Cache;

class PaySdk
{


    private $api_url = '';
    private $notify_url = '';
    private $merchant = '';
    private $secretkey = '';




    public function __construct()
    {
        $this->api_url = '';
        $this->merchant = '';
        $this->secretkey = '';
    }


    public function pay($param, $config = [])
    {
        if (isset($config['appid']) && !empty($config['appid'])) {
            $this->merchant = $config['appid'];
        }
        if (isset($config['secret']) && !empty($config['secret'])) {
            $this->secretkey = $config['secret'];
        }
        if (isset($config['apiurl']) && !empty($config['apiurl'])) {
            $this->api_url = $config['apiurl'];
        }
        $merchant_no = $this->merchant;
        $orderNo     = trim($param['orderid']);
        $amount      = sprintf('%.2f',$param['amount']);
        $firstname   = 'pay';
        $mobile      = rand(6,9).rand(100000000,999999999);
        $email       = $mobile.'@gmail.com';
        $surl        = $config['redirect_url'];
        $furl        = $config['redirect_url'];
        $remark      = trim($param['orderid']);
        $data = [
            "orderNo"   => $orderNo,
            "amount"    => $amount,
            'firstname' => $firstname,
            'mobile'    => $mobile,
            'email'     => $email,
            'surl'      => $surl,
            'furl'      => $furl,
            'remark'    => $orderNo
        ];
        $data_str = $this->getUrlStr($data);
        $sign = $this->sign($data_str, $this->secretkey);
        $header = [
            'Content-Type: application/json; charset=utf-8',
            'X-SIGN: ' . $sign,
            'X-SERVICE-CODE: ' . $config['code']
        ];
        $result =$this->curl_post_content($this->api_url .'/portal/createH5PayLink',$data, $header);
        save_log('joypay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['code'])) {
            $res['message'] ='Http Request Invalid';
        }
        $returl='';
        if(is_array($res)){
            if($res['code']=='0000')
                $returl= $res['data']['linkUrl'];
        }

        return $returl;
    }



    private  function sign($data, $extra) {
        // 私钥
        $privateKeyBase64 = "-----BEGIN RSA PRIVATE KEY-----\n";
        $privateKeyBase64.= wordwrap($extra, 64, "\n", true);
        $privateKeyBase64.= "\n-----END RSA PRIVATE KEY-----\n";
        // 签名
        openssl_sign($data, $signature, $privateKeyBase64, OPENSSL_ALGO_SHA512);
        return base64_encode($signature);
    }

    private function getUrlStr($data) {
        ksort($data);
        $urlStr = [];
        foreach ($data as $k => $v) {
            if (strlen($v) > 0 && $k != 'sign') {
                $urlStr[] = $k . '=' . rawurlencode($v);
            }
        }
        return join('&', $urlStr);
    }    


    private function curl_post_content($url, $data = null, $header = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $output = curl_exec($ch);
        curl_close($ch);;
        return $output;
    }
}