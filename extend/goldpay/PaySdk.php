<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace goldpay;

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
        $this->api_url = 'https://www.goldpays.in';
        $this->merchant = 'C1637051699435';
        $this->secretkey = '9AmkzYW1SEJjGILsg8bGZGIQWPQ8SIluDXPI4b';
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

        $data = [
            'merchant' => $this->merchant,
            'orderId' => $param['orderid'],
            'amount' => strval($param['amount']),
            'customName' => 'ID:'.$param['AccountID'],
            'customMobile' => '12345678',
            'customEmail' => 'chiefpony@gmail.com',
            'notifyUrl' => $config['notify_url'],
            'callbackUrl' =>$config['redirect_url'],
        ];
        $data['sign'] =$this->genSign($data,$config['secret']);
        $header = [
            'Content-Type: application/json; charset=utf-8',
        ];
        $result =$this->curl_post_content($this->api_url .'/openApi/pay/createOrder', json_encode($data), $header);
        save_log('goldpay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['code'])) {
            $res['message'] ='Http Request Invalid';
            //exit('Http Request Invalid');
        }

        $returl ='';
        if($res['code']==200) {
            if (!empty($res['data']['url'])) {
                $returl = $res['data']['url'];
            }
        }
        return $returl;
    }


    public function payout(){


    }


    private function genSign($data,$Md5key)
    {
        ksort($data);
        $md5str = '';
        foreach ($data as $key => $val) {
            if (!empty(trim($val))) {
                $md5str = $md5str . $key . '=' . $val . '&';
            }
        }
        return strtolower(md5($md5str . 'key=' . $Md5key));
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