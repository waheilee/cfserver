<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace doipa;

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
        $this->api_url = 'https://www.mercadosuperfast.com';
        $this->appid = '6a99013206ef21aa1947e12e8a6e85b6';
        $this->secret = 'SCJBSmamI3rFfaAWlaBStl6Pz2dHDeSGtPRhCgz5DYKnqQiYCetmD3uXcepS';
    }


    public function pay($param, $config = [])
    {
        if (isset($config['appid']) && !empty($config['appid'])) {
            $this->appid = $config['appid'];
        }
        if (isset($config['secret']) && !empty($config['secret'])) {
            $this->secret = $config['secret'];
        }
        if (isset($config['apiurl']) && !empty($config['apiurl'])) {
            $this->api_url = $config['apiurl'];
        }

        $data = [
            'merchantId'=> $this->appid,
            'version'=>'V2',
            'channel' => 'cashfree',
            'orderNo' => $param['orderid'],
            'amount' => $param['amount'],
            'phone'=>'9774867890',
            'name'=>'smille',
            'email'=>'amy@gmail.com',
            'notifyUrl' => $config['notify_url'],
            'returnUrl' =>$config['redirect_url'],
            'nativeApp' => 0
        ];
        $data['sign'] =$this->genSign($data,$config['secret']);
        $header = [
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
        ];
        $result =$this->curl_post_content($this->api_url .'/payment/createOrder.do', http_build_query($data), $header);
        save_log('doipay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['code'])) {
            $res['message'] ='Http Request Invalid';
            //exit('Http Request Invalid');
        }
        $returl ='';
        if(!empty($res['data']['jumpUrl'])){
            $returl = $res['data']['jumpUrl'];
        }
        return $returl;
    }

    private function genSign($data,$Md5key)
    {
        ksort($data);
        $md5str = '';
        foreach ($data as $key => $val) {
            $md5str = $md5str . $key . '=' . $val . '&';
        }
        $md5str = substr($md5str,0,-1);  //去掉最后一个 & 字符
        return base64_encode(hash_hmac("sha1", $md5str, $this->secret, $raw_output = TRUE));
    }


    private function curl_post_content($url, $data = null, $header = [])
    {
        $ch = curl_init();
        if (substr_count($url, 'https://') > 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        if (!empty($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }


}