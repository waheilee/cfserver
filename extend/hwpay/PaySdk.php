<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace hwpay;

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
        $order_no = trim($param['orderid']);
        $amount       = $param['amount'];
        $notify_url   = trim($config['notify_url']);
        $redirect_url   = trim($config['redirect_url']);
        $mobile      = rand(6,9).rand(100000000,999999999);
        $email       = $mobile.'@gmail.com';
        $uid         = strval($param['roleid']);
        $data = [
            'orderId' =>$order_no,
            'orderTime'=>date('YmdHis'),
            'amount'=>$amount*100,
            'currencyType'  =>'INR',
            'goods'=>'pay',
            'notifyUrl'   =>$notify_url,
            'callBackUrl' =>$redirect_url,
            'userId'=>$uid,
            'phone'=>$mobile,
            'name'=>'pay',
            'email'=>$email  
        ];
        $sign = $this->genSign($data,$this->secretkey);
        $header = [
            "Content-Type: application/json; charset=utf-8;"
        ];
        $head = [
            "mchtId"=>$merchant_no,
            "version"=>"20",
            "biz"=>"ca001"
        ];
        $post_data = [
            'head'=>$head,
            'body'=>$data,
            'sign'=>$sign
        ];
        $result =$this->curl_post_content($this->api_url,json_encode($post_data), $header);
        save_log('hwpay','提交参数:'.json_encode($post_data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['head']['respCode'])) {
            $res['message'] ='Http Request Invalid';
        }
        $returl='';
        if(is_array($res)){
            if($res['head']['respCode']=='0000')
                $returl= $res['body']['payUrl'];
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
        return md5($str);
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