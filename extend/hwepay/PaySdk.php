<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace hwepay;

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
        $merchant_no  = $this->merchant;
        $out_order_no = trim($param['orderid']);
        $amount       = floor($param['amount']);
        $pay_type     = $config['code'];
        $notify_url   = trim($config['notify_url']);
        $data = [
            'merchant_no'  =>$merchant_no,
            'out_order_no' =>$out_order_no,
            'amount'       =>$amount,
            'pay_type'     =>$pay_type,
            'notify_url'   =>$notify_url
        ];
        $sign = md5($merchant_no.$out_order_no.$amount.$pay_type.$notify_url.$this->secretkey);
        $data['sign'] = $sign;
        $header = [
            
        ];
        $result =$this->curl_post_content($this->api_url .'/api/pay',http_build_query($data), $header);
        save_log('hwepay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['code'])) {
            $res['message'] ='Http Request Invalid';
        }
        $returl='';
        if(is_array($res)){
            if($res['code']=='1')
                $returl= $res['data']['pay_url'];
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
        return strtoupper(md5($str));
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