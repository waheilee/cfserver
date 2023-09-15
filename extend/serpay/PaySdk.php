<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace serpay;

use Utility\Utility;
use think\facade\Cache;

class PaySdk
{


    private $api_url = '';
    private $notify_url = '';
    private $merchant = '';
    private $secretkey = '';
    private $orgno ='8211200824';




    public function __construct()
    {
        $this->api_url = 'https://api.serpayment.com';
        $this->merchant = '21122500002381';
        $this->secretkey = '47DBA7117DF7ADCBDAB6F0A5DAEA54C9';
        $this->orgno ='8211200824';
    }


    public function pay($param, $config = [])
    {
        if (isset($config['appid']) && !empty($config['appid'])) {
            $this->merchant = $config['appid'];
        }

        if (isset($config['orgno']) && !empty($config['orgno'])) {
            $this->orgno = $config['orgno'];
        }

        if (isset($config['secret']) && !empty($config['secret'])) {
            $this->secretkey = $config['secret'];
        }
        if (isset($config['apiurl']) && !empty($config['apiurl'])) {
            $this->api_url = $config['apiurl'];
        }


        $clentip = getClientIP();
        $rand = rand(6,15);
        $rand_ext= rand(0,2);
        $mailext =['@gmail.com','@hotmail.com','@mail.yahoo.com'];
        $mailname=$this->random_str($rand);
        $usermail = $mailname.$mailext[$rand_ext];

        $data = [
            'version'=>'2.1',
            'orgNo' =>$this->orgno,
            'custId' => $this->merchant,
            'custOrderNo' => 'NEICHONG_'.$param['orderid'],
            'tranType'=>'0101',
            'clearType'=>'01',
            'payAmt' => strval($param['amount']*100),
            'backUrl' =>$config['notify_url'],
            'frontUrl' =>$config['redirect_url'],
            'goodsName'=>'ID:'.$param['roleid'],
            'orderDesc'=>'ID:'.$param['roleid'],
            'buyIp' =>$clentip,
            'userName'=> strval($param['roleid']),
            'userEmail'=>$usermail,
            'userPhone'=> '12345678',
            'countryCode'=>'IN',
            'currency' =>'INR'
        ];
        $data['sign'] =$this->genSign($data,$config['secret']);
        $header = [
            'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
        ];
        $result =$this->curl_post_content($this->api_url .'/cashier/pay.ac', http_build_query($data), $header);
        save_log('serpay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['code'])) {
            $res['message'] ='Http Request Invalid';
            //exit('Http Request Invalid');
        }
        $returl ='';
        if($res['code']=='000000') {
            if (!empty($res['contentType'])) {
                if($res['contentType']=='01'){
                    $returl = $res['busContent'];
                }
                else if($res['contentType']=='03'){
                    $ret= create_folders('./public/order');
                    file_put_contents('./public/order/'.$param['orderid'].'.html',$res['busContent']);
                    $domain = config('paydomain');
                    $returl = $domain.'/public/order/'.$param['orderid'].'.html';
                }
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







}