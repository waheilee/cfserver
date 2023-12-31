<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace easypay;

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
        $this->api_url = 'https://pay.gamegods2020.com/pay';
        $this->appid = '10184';
        $this->secret = 'de9968c33b8640a3ba20ebbbc50c0e97';
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
            'merchantId' => $this->appid,
            'orderId' => $param['orderid'],
            'coin' => $param['amount'],
            'productId' => 1000,
            'goods' => 'iPhone12',
            'attach' => '123|123',
            'notifyUrl' => $config['notify_url'],
            'redirectUrl' =>$config['redirect_url'],
        ];
        $data['sign'] =$this->genSign($data,$config['secret']);
        $header = [
            'Content-Type: application/json; charset=utf-8',
        ];
        $result =$this->curl_post_content($this->api_url .'/v1/pay/createOrder', json_encode($data), $header);
        save_log('easypay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['code'])) {
            $res['message'] ='Http Request Invalid';
            //exit('Http Request Invalid');
        }
        $returl ='';
        if(!empty($res['data']['url'])){
            $returl = $res['data']['url'];
        }
        return $returl;
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
        return strtoupper(md5($md5str . 'key=' . $Md5key));
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