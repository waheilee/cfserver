<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace swiftpay;

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
        $this->api_url = 'https://api.swiftpay.link';
        $this->merchant = '10045';
        $this->secretkey = '5n21v1gefiznp914mpavhx6ddcw7bg7f';
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
            'pay_memberid' =>intval($this->merchant),
            'pay_orderid' =>trim($param['orderid']),
            'pay_amount' => $param['amount'],
            'pay_applydate' =>$param['paytime'],
            'pay_notifyurl' => trim($config['notify_url']),
            'pay_callbackurl' => trim($config['redirect_url'])
        ];
        $sign =$this->genSign($data,$config['secret']);
        $data['pay_md5sign'] = $sign;
        $data['pay_format'] = 'json';
        $header = [
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
        ];
        $result =$this->curl_post_content($this->api_url .'/Pay_Index.html',http_build_query($data), $header);
        save_log('swiftpay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['status'])) {
            $res['message'] ='Http Request Invalid';
            //exit('Http Request Invalid');
        }
        //{"code":0,"platformOrderid":"165794044655867121","backUrl":"http://www.ssspay.org/starpay/pay/order/webNowOpen/165794044655867121","payLink":""}
        $returl='';
        if(is_array($res)){
            if($res['status']=='success')
                $returl= $res['pay_url'];
        }
        else{
            $domain = config('paydomain');
            if(!empty($result))
            {
                file_put_contents('./order/'.$param['orderid'].'.html',$result);
                $returl = $domain.'/order/'.$param['orderid'].'.html';
            }
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