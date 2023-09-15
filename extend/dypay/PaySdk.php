<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace dypay;
use think\facade\Cache;

class PaySdk
{


    private $api_url = '';
    private $notify_url = '';
    private $appid = '';
    private $secret = '';

//平台公钥
    private $publicKey = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDByR7wOkfnmzcQ6OdGsvIegx08mpaeT4R01XY6+FmgablZ8dZ/KVGn1Y+m5kvSfn3piIH8Ma5REu7xXut1Wrv/rHixdwQ4yaUlbQnaMO1JZwEyTe/3sePnPHePC/enghopJrWRq2nTdbVO7snFeX0/1qNIWAuFFgPOUC4qphxRrQIDAQAB';
//商户私钥
    private $privateKey = 'MIICeAIBADANBgkqhkiG9w0BAQEFAASCAmIwggJeAgEAAoGBAOQHYr5Fspw/63MmTYxlfdPw6GnpAjwRZjk0I5Z4qKGoBj1bS76Zr49XYNKyNeATRL9hsoPR3v4K++itfsz3kaIZBXP0VXH0hNSDN+tABlWqQolyXHaO3UD04eedqoOq8SaZk//3QyWgtYPmzj47McJBWZTn1q2/oQ2od/TMgmqVAgMBAAECgYEAtLSlq+PQB8Mf88EG85v6e1sO09+zxaaEPBD1ouk7ueBOEZGoFQP1/MJiGJbh2xFqCcCCl7RZ4zkRKPNU6VnILg5jp4X0gLFMgx/prpmrskr5U7b3Hx2dYs9I/WyQskTeqXQkxkQjl7S0YFoV4MHhkD3/yepj4unodJFK3To3GAECQQD2Ltl4Ve6bynVmKwunoZE7DtvHdInDy+9rm78M6EdOO5ORHbd9MxsfeBso7Kb0pqw8xNvIOJTrdtaBtu/iO6tRAkEA7R81zbkhrnguUBeveGbqptL+qW7V0d4NphS5ux6D3c8Oceo+fudd01vnzYt+jVqSoPkAhrTnEzOv2P6e/q5yBQJBAMYJigezmO7aPvahSg7feeT4XvRkWy6Wr1LxRw8rC7FzW5IxRZoBsp/uDmstdGD6czOvaN34JlQElSpj7zUeqwECQDokuwa07KNhaMnO5QH7CnLZrgRR3zBU6LfewSQ2+VK8YOhh7e0kQod/M7ndCK0UlnvOUui1FyxIMkhdNxNwJxkCQQClCDc3bVUhCRcgk/yYPoWwsUNyeEVRJXym1DpjMG5kruWJjQpnUXHI/td8g5svgkDwfN1KNYoqK008672/ifEh';


    public function __construct()
    {
        $this->api_url = 'https://mblfc.dyb360.com';
        $this->appid = ' 861100000012931';
        $this->secret = '70EAFAE6E124E359A0ABA76F06533EDA';
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
            'mer_no'=>$this->appid,
            'mer_order_no'=>$param['orderid'],
            'pname'=>'dsraew',
            'pemail'=>'test@gmail.com',
            'phone'=>'9774867890',
            'order_amount'=>$param['amount'],
            'ccy_no'=>$config['currency'],
            'busi_code'=>$config['code'],
            'notifyUrl'=>$config['notify_url'],
            'pageUrl'=>$config['redirect_url']
        ];
        $sign_data = $this->gensign($data);
        //$data['sign'] = urlencode($sign);
        $header = [
            'Content-Type: application/json; charset=utf-8',
        ];
        $result =$this->curl_post_content($this->api_url .'/ty/orderPay', json_encode($sign_data), $header);
        save_log('dypay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['status'])) {
            $res['message'] ='Http Request Invalid';
            //exit('Http Request Invalid');
        }
        $returl ='';
        if($res['status']=='SUCCESS')
            if(!empty($res['order_data'])){
                $returl = $res['order_data'];
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


    public function gensign($data){
        $utility =new Utility();
        $str_sign = $utility->encrypt($data);
        return $str_sign;
    }

    public function verify($data){
        //验签
        ksort($data);
        reset($data);
        $arg = '';
        foreach ($data as $key => $val) {
            //空值不参与签名
            if ($val == '' || $key == 'sign') {
                continue;
            }
            $arg .= ($key . '=' . $val . '&');
        }
        $sig_data =  substr($arg,0,strlen($arg)-1);
        $rsa = new Rsa($this->publicKey, '');
        if ($rsa->verify($sig_data, $data['sign']) == 1) {
            return true;
        }
        return false;
    }



    private function genMD5Sign($data,$Md5key)
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




}