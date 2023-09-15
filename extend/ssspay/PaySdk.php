<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace ssspay;

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
        $this->api_url = 'https://www.ssspay.org';
        $this->merchant = '10753';
        $this->secretkey = 'bc882ad4e694df0ab0186707897c2977';
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
            'userid' =>intval($this->merchant),
            'orderid' =>trim($param['orderid']),
            'stamp' =>time(),
            'channelcode'=>intval($config['code']),
            'notifyurl' => trim($config['notify_url']),
            'amount' => $param['amount']
        ];
        $sign =$this->genSign($data,$config['secret']);
        $data['email'] = 'chiefpony@gmail.com';
        $data['firstname'] = 'Jim';
        $data['lastname'] = 'Green';
        $data['phone']='13122336688';
        $data['backUrl'] =0;
        $data['sign'] = $sign;

        $header = [
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
        ];
        $result =$this->curl_post_content($this->api_url .'/starpay/pay/orders',http_build_query($data), $header);
        save_log('ssspay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['code'])) {
            $res['message'] ='Http Request Invalid';
            //exit('Http Request Invalid');
        }
        //{"code":0,"platformOrderid":"165794044655867121","backUrl":"http://www.ssspay.org/starpay/pay/order/webNowOpen/165794044655867121","payLink":""}
        $returl='';
        if(is_array($res)){
            if($res['code']==0)
                $returl= $res['backUrl'];
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