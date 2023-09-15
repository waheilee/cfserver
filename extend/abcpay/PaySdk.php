<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace abcpay;

use Utility\Utility;
use think\facade\Cache;

class PaySdk
{


    //ID: 9025672
    //APPKEY: -zQZd--ajKJUY-Pn2XZx2Ai9hlI
    //APPID: 0cccb6669b4e45b19b5dfbd857b011c2
    private $api_url = '';
    private $merchant_id='';
    private $notify_url = '';
    private $appid = '';
    private $secret = '';

    public function __construct()
    {
        $this->api_url = 'https://';
        $this->merchant_id= 9025672;
        $this->appid = '0cccb6669b4e45b19b5dfbd857b011c2';
        $this->secret = '-zQZd--ajKJUY-Pn2XZx2Ai9hlI';
    }


    public function pay($param, $config = [])
    {
        if (isset($config['merchantid']) && !empty($config['merchantid'])) {
            $this->merchant_id = $config['merchantid'];
        }

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
            'memberid'=>$this->merchant_id,
            'appid'=> $this->appid,
            'orderid'=>$param['orderid'],
            'amount'=>strval($param['amount']),
            'notifyurl'=>$config['notify_url'],
            'returnurl'=>$config['redirect_url'],
            'reqtime' =>time(),
            'appkey' =>$config['secret']
        ];
        $token =$this->getToken($param, $config);
        if(empty($token))
            return '';
        $data['token'] =$token;
        $data['sign'] = $this->genSign($data,$config['secret']);
        $header = [
            'Authorization:'.$token,
            'Content-Type: application/json; charset=utf-8;',
        ];
        $result =$this->curl_post_content($this->api_url .'/v1/payurl', json_encode($data), $header);
        save_log('abcpay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['code'])) {
            $res['message'] ='Http Request Invalid';
            //exit('Http Request Invalid');
        }
        $returl ='';
        if($res['code']==200)
            if(!empty($res['data']['url'])){
                $returl = $res['data']['url'];
            }
        return $returl;
    }


    private  function  getToken($param,$config = []){
        $postadata =[
            'memberid'=>$this->merchant_id,
            'appid'=> $this->appid,
            'orderid'=>$param['orderid'],
            'reqtime'=>time(),
            'appkey'=>$config['secret']
        ];
        $sign = $this->genSign($postadata,$config['secret']);
        $postadata['sign'] =$sign;
        $header = [
            'Content-Type: application/json; charset=utf-8',
        ];
        $result =$this->curl_post_content($this->api_url .'/v1/accesstoken', json_encode($postadata), $header);
        save_log('abcpay','提交参数:'.json_encode($postadata).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        $token ='';
        if (!isset($res['code'])) {
            return '';
        }

        if($res['code']==200){
            $token=$res['data']['token'];
        }
        return $token;
    }




    private function genSign($data,$Md5key)
    {
        ksort($data);
        $md5str = '';
        foreach ($data as $key => $val) {
            if (trim($val)!=='') {
                $md5str = $md5str . $key . '=' . $val . '&';
            }
        }
        $md5str = trim($md5str,'&');
        return strtoupper(md5($md5str));// . 'key=' . $Md5key
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


    private static function getMillisecond() {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
    }


}