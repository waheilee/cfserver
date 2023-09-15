<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace xjpay;

use Utility\Utility;
use think\facade\Cache;

class PaySdk
{


    private $api_url = '';
    private $notify_url = '';
    private $merchant = '';
    private $secret = '';



    public function __construct()
    {
        $this->api_url = '';
        $this->merchant = '';
        $this->secret = '';
    }


    public function pay($param, $config = [])
    {

        if (isset($config['appid']) && !empty($config['appid'])) {
            $this->merchant = $config['appid'];
        }
        if (isset($config['secret']) && !empty($config['secret'])) {
            $this->secret = $config['secret'];
        }
        if (isset($config['apiurl']) && !empty($config['apiurl'])) {
            $this->api_url = $config['apiurl'];
        }
        $merchant_no = $this->merchant;
        $orderid     = trim($param['orderid']);
        $amount      = sprintf('%.2f',$param['amount']);;
        $notify_url  = trim($config['notify_url']);
        $mobile      = rand(6,9).rand(100000000,999999999);
        $email       = $mobile.'@gmail.com';
        $timestamp   = time();
        $data = [
            'mchOrderId'  =>$orderid,
            'amount'      =>$amount,
            'currency'  =>'BRL',
            'productinfo' =>$orderid,
            'firstname'   =>'pay',
            'lastname'    =>'pay',
            'email'       =>$email,
            'phone'       =>$mobile,
            'callbackUrl' =>$notify_url,
        ];

        $header = [
            'Content-Type:application/json;charset=UTF-8',
            'serviceName:api.pay',
            'method:pay',
            'mchId:'.$merchant_no,
            'signType:SHA512',
            'timestamp:'.$timestamp,
        ];
        $sign_string = $merchant_no.'api.pay'.'pay'.$timestamp.'SHA512'.json_encode($data).$this->secret;
        $sign = hash("sha512", $sign_string);
        $header[] = 'sign:'.$sign;
        save_log('xjpay','提交参数:'.json_encode($data));
        $result =$this->curl_post_content($this->api_url,json_encode($data), $header);
        save_log('xjpay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['resultCode'])) {
            $res['message'] ='Http Request Invalid';
        }
        $returl='';
        if(is_array($res)){
            if($res['resultCode']=='000000')
                $returl= $res['data'][0]['checkoutUrl'];
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
        $str =$md5str . 'key=' . $Md5key;
        return strtolower(md5($str));
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
