<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace toppay;

use Utility\Utility;
use think\facade\Cache;
use app\model\MasterDB;
use app\model\UserDB;

class PaySdk
{


    private $api_url = '';
    private $notify_url = '';
    private $merchant = '';
    private $secret = '';
    private $private_key = '';
    private $ret_text = 'success';


    public function __construct()
    {
        $this->api_url = '';
        $this->merchant = '';
        $this->secret = '';
        $this->private_key = '';
    }


    public function pay($param, $config = [])
    {
        if (isset($config['appid']) && !empty($config['appid'])) {
            $this->merchant = $config['appid'];
        }
        if (isset($config['private_key']) && !empty($config['private_key'])) {
            $this->private_key = $config['private_key'];
        }
        if (isset($config['secret']) && !empty($config['secret'])) {
            $this->secret = $config['secret'];
        }
        if (isset($config['apiurl']) && !empty($config['apiurl'])) {
            $this->api_url = $config['apiurl'];
        }
        $merchant_no = $this->merchant;
        $orderid     = trim($param['orderid']);
        $amount      = sprintf('%.0f',$param['amount']);;
        $notify_url  = trim($config['notify_url']);
        $mobile      = rand(6,9).rand(100000000,999999999);
        $username    = chr(rand(65,90)).chr(rand(97,122)).chr(rand(97,122)).chr(rand(97,122)).chr(rand(97,122)).chr(rand(97,122));
        $email       = $mobile.'@gmail.com';
        $timestamp   = time();
        $data = [
            'merchant_no'  =>$merchant_no,
            'out_trade_no' =>$orderid,
            'description'  =>'pay_description',
            'title'        =>'pay_title',
            'pay_amount'   =>round($amount,2).'',
            'notify_url'   =>$notify_url
        ];

        $header = [
            'Content-Type:application/json;charset=UTF-8',
        ];
        $data['sign'] = $this->buildSign($data,$this->private_key);
        $result =$this->curl_post_content($this->api_url.'/api/trade/payin',json_encode($data), $header);
        save_log('toppay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['code'])) {
            $res['message'] ='Http Request Invalid';
        }
        $returl='';
        if(is_array($res)){
            if($res['code']==0)
                $returl= $res['data']['payment_link'];
        }

        return $returl;
    }

    //回调地址 /client/Pay_Notify/templatepay_notify
    public function notify($params,$header=[],$channel,$logname)
    {
        try {
            //参数
            $sign                  = $params['sign']??'';
            $data['json']          = json_encode($params);
            $data['orderid']       = $params['out_trade_no']??'';
            $data['realmoney']     = $params['pay_amount']??'';
            $data['transactionId'] = $params['trade_no']??'';
            $data['code']          = $params['status'];
            $data['status']        = $params['status']??'' == '1' ? 1 : 0;
            //sign认证
    
            $checksign             = $this->verifySign($params,$channel['public_key']);
            if ($checksign) {
                $sign = $checksign = 1;
            } else {
                exit('fail');
            }
            (new \paynotify\PayNotify())->notify($data,$sign,$checksign,$channel,$logname);
        } catch (Exception $ex) {
            save_log($logname, 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            exit('fail');
        }
    }

    //回调地址 /client/Pay_Notify/templatepay_outnotify
    public function outnotify($params,$header=[],$channel,$logname){
        try {
            //参数
            $sign                  = $params['sign']??'';
            $data['json']          = json_encode($params);
            $data['orderid']       = $params['out_trade_no']??'';
            $data['realmoney']     = $params['pay_amount']??'';
            $data['transactionId'] = $params['trade_no']??'';
            $data['code']          = $params['status'];
            switch ($data['code']) {
                case '1':
                    //成功
                    $data['status'] = 1;
                    break;
                case '2':
                    //失败
                    $data['status'] = 2;
                    break;
                case '0':
                    //处理中
                    $data['status'] = 3;
                    break;
                default:
                    $data['status'] = 0;
                    break;
            }
            $checksign             = $this->verifySign($params,$channel['public_key']);
            if ($checksign) {
                $sign = $checksign = 1;
            } else {
                exit('fail');
            }

            (new \paynotify\PayNotify())->outnotify($data,$sign,$checksign,$channel,$logname);
        }catch (Exception $ex){
            save_log($logname,'Exception:' . $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            exit('fail');
        }
    }


/**
 * 加密加签
 */
public function buildSign(array $params, string $privateKey): string
{
    ksort($params);
    $str = [];
    foreach ($params as $k => $v) {
        if ($v === '') {
            continue;
        }
        $str[] = $k . '=' . $v;
    }
    $send = implode('&', $str) . '&';
    // 获取用户公钥，并格式化
    $privateKey = "-----BEGIN PRIVATE KEY-----\n"
        . wordwrap(trim($privateKey), 64, "\n", true)
        . "\n-----END PRIVATE KEY-----";
    $content = '';
    $privateKey = openssl_pkey_get_private($privateKey);
    foreach (str_split($send, 117) as $temp) {
        openssl_private_encrypt($temp, $encrypted, $privateKey);
        $content .= $encrypted;
    }
    return base64_encode($content);
}

/**
 * 解密验签
 */
public function verifySign(array $data, string $publicKey): bool
{
    if (isset($data['sign'])) {
        $sign = base64_decode($data['sign']);
        unset($data['sign']);
    } else {
        return false;
    }
    ksort($data);
    $str = [];
    foreach ($data as $k => $v) {
        if ($v === '') {
            continue;
        }
        $str[] = $k . '=' . $v;
    }
    $send = implode('&', $str) . '&';
    // 获取用户公钥，并格式化
    $publicKey = "-----BEGIN PUBLIC KEY-----\n"
        . wordwrap(trim($publicKey), 64, "\n", true)
        . "\n-----END PUBLIC KEY-----";
    $publicKey = openssl_pkey_get_public($publicKey);
    $result = '';
    foreach (str_split($sign, 128) as $value) {
        openssl_public_decrypt($value, $decrypted, $publicKey);
        $result .= $decrypted;
    }
    return $result === $send;
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
