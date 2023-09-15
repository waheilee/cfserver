<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace aupay;

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
        $this->appid = '10002';
        $this->secret = 'GDIWFNUZJHV6K4M0BC';
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
            'version'=>'1.0.0',
            'memberid' => $this->appid,
            'orderid' => $param['orderid'],
            'amount' => $param['amount']*100,
            'orderdatetime' => $param['paytime'],
            'paytype' => $config['pay_type'],
            'notifyurl' => $config['notify_url'],
            'signmethod' => 'md5'
        ];
        $data['sign'] =$this->genSign($data,$config['secret']);
        $data['returnJson'] ='json';
        $header = [
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
        ];
        $result =$this->curl_post_content($this->api_url .'/Pay.html', http_build_query($data), $header);
        save_log('aupay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        $returl ='';
        if (!isset($res['url'])) {
            $res['message'] ='Http Request Invalid';
            //exit('Http Request Invalid');
        }else{
            $returl = $res['url'];
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
        $md5str = substr($md5str,0,-1);  //去掉最后一个 & 字符
        return strtoupper(md5($md5str . $Md5key));
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