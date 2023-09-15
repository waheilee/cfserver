<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace inpays;

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
        $this->api_url = 'https://m163903470633830fate.yvk.net';
        $this->merchant = 'M163903470633830';
        $this->secretkey = '796f9480e2d5245a0254bc55c7b51854';
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

        $rand = rand(6,15);
        $rand_ext= rand(0,2);
        $mailext =['@gmail.com','@hotmail.com','@mail.yahoo.com'];
        $mailname=$this->random_str($rand);
        $usermail = $mailname.$mailext[$rand_ext];

        $data = [
            'merchantid' => $this->merchant,
            'out_trade_no' => $param['orderid'],
            'total_fee' => strval($param['amount']),
            'notify_url' => $config['notify_url'],
            'reply_type' => 'URL',
            'timestamp'=>time(),
            'customer_name' => 'ID:'.$param['roleid'],
            'customer_mobile' => '12345678',
            'customer_email' => $usermail
        ];
        $data['sign'] =$this->genSign($data,$config['secret']);
        $header = [
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
        ];
        $result =$this->curl_post_content($this->api_url .'/inpays/payin/unifiedorder', http_build_query($data), $header);
        save_log('inpays','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['code'])) {
            $res['message'] ='Http Request Invalid';
        }
        $returl ='';
        if($res['code']==0) {
            if (!empty($res['data']['url'])) {
                $returl = $res['data']['url'];
            }
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