<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace beepay;

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
        $this->api_url = 'https://api.bee-earning.com/order';
        $this->appid = 'test';
        $this->secret = '123456';
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


        $rand = rand(6,15);
        $rand_ext= rand(0,2);
        $mailext =['@gmail.com','@hotmail.com','@mail.yahoo.com'];
        $mailname=$this->random_str($rand);
        $usermail = $mailname.$mailext[$rand_ext];

        $data = [
            'amount' => $param['amount'],
            'callbackUrl' => $config['notify_url'],
            'channelId' => $this->appid,
            'channleOid'=> $param['orderid'],
            'email'=>$usermail,
            'firstName'=>'jim',
            "lastName"=> "green",
            'mobile' => '9774867890',
            'notifyUrl' => $config['notify_url'],
            'payType' => 1,
            'remark' => 'ID:'.$param['roleid'],
            'timestamp' => $this->getMillisecond()
        ];
        $data['sign'] =md5($data['channelId'].$data['channleOid'].$data['amount'].$config['secret']);
        $header = [
            'Content-Type: application/json; charset=utf-8',
        ];
        $result =$this->curl_post_content($this->api_url .'/order/submit', json_encode($data), $header);
        save_log('beepay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['code'])) {
            $res['message'] ='Http Request Invalid';
            //exit('Http Request Invalid');
        }
        $returl ='';
        if($res['code']=='0000')
        if(!empty($res['data']['payUrl'])){
            $returl = $res['data']['payUrl'];
        }
        return $returl;
    }



    private function random_str($length)
    {
        //生成一个包含  小写英文字母, 数字 的数组
        $arr = range('a', 'z');
        $str = '';
        $arr_len = count($arr);
        for ($i = 0; $i < $length; $i++) {
            $rand = mt_rand(0, $arr_len - 1);
            $str .= $arr[$rand];
        }
        return $str;
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


    private static function getMillisecond() {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f',(floatval($t1)+floatval($t2))*1000);
    }


}