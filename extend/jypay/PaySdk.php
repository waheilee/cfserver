<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */
//墨西哥
namespace jypay;

use Utility\Utility;
use think\facade\Cache;
use app\model\MasterDB;
use app\model\UserDB;
use app\model\BankDB;

class PaySdk
{


    private $api_url = '';
    private $notify_url = '';
    private $merchant = '';
    private $appid = '';
    private $secret = '';
    private $ret_text = 'success';


    public function __construct()
    {
        $this->api_url = '';
        $this->merchant = '';
        $this->secret = '';
    }


    public function pay($param, $config = [])
    {

        if (isset($config['merchant']) && !empty($config['merchant'])) {
            $this->merchant = $config['merchant'];
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
        $merchant = $this->merchant;
        $appid = $this->appid;
        $orderid = trim($param['orderid']);
        $amount = sprintf('%.2f', $param['amount']);
        $notify_url = trim($config['notify_url']);
        $mobile = rand(6, 9) . rand(100000000, 999999999);
        $username = chr(rand(65, 90)) . chr(rand(97, 122)) . chr(rand(97, 122)) . chr(rand(97, 122)) . chr(rand(97, 122)) . chr(rand(97, 122));
        $email = $mobile . '@gmail.com';
        $timestamp = time();

        // $amount = (int)$amount;

// {
//   "mer_no": "xxx",
//   "phone": "9852146882",
//   "pname": "ZhangSan",
//   "order_amount": "100",
//   "sign": "hv_OoRZejna_3baya6wSRadYNvV718BQ_QLthR56OWkLx-4FrOKCawwM_4y33rcjpLM8bwqr_mRYGMQ19PKKmBB5HABQXhBBZ76x2Dqyl77kRSOHlt3akG8mKGiq8p8etP-jS8va2eHpLS96lTt-Kc5wcgrlFjFwZ1s8xlLa0NQOIlED1afGnLEsZRXyf0oyZY-NpOUDJYHGus6aTfbfR5gd6Z-yQ8h4ELK8D_UNJTUQBVkxL4ffXI2KR2fGoWyaOjX6ZCXwgA9yg5Ykp5whwtzckE0fQzZeFJPXKn_ad_tTGcdx5MGnKcm8I_vT39a8HjfQcT3VWskGDsNr5Tt8VA",
//   "goods": "test",
//   "notifyUrl": "http://www.google.com",
//   "pageUrl": "http://www.baidu.com",
//   "ccy_no": "INR",
//   "pemail": "test@mail.com",
//   "busi_code": "100303",
//   "mer_order_no": "testOrder1650441426386"
// }
        $key = 'MIICeAIBADANBgkqhkiG9w0BAQEFAASCAmIwggJeAgEAAoGBAMO0Tf6BZYsGCrUgGb7GbSSvOEhqpERa4VWEQSL6fPlTMZtjswc8v7Gu3zCS4/SltuaAKmZZOvS/clHRO/iVaLX7GrQV1nIWL7/pwsbJkMNf1HHohakZyicb/yP3d4Osps2u7n099jL3cQKi9O3cPahs5yhQUD6j7Wa4ECCyb+DXAgMBAAECgYEAsiwrffQskH+1q+VHyyo4H9fus+9zElBzKjo4WzIWKaAWX9RLH+Gs3IXK6RwysX9Vn1E8SOYgCMdruxV1NgJAyyXX1mlpvpSyipDNa1eShyKguOd0iYmJiVKKMaTqEOiWkpnFeJVeNbxA0WLNGheZ2gGJgElswBpDJMPFXkZ606ECQQDlugV8lVXIcFlDjr7pqSt6DJBc0gSgWHd2h/l+5BaXbU2dkxndGYpFnOeFvY/rTyPOPznO5dNyF6iaGN5IlXeZAkEA2hYr1Z8o0laI1KCHkwbyFq0v7u1qLsIWXJ9ifgR90DEeuOlIpQbdWtkea2NVKxGkZe/gWfAspIZLXdwz+N6h7wJBAJGymlo6aE6YmrSTKxgM1+svXrvP42lC0nmVobJNvNpLU4eVzTiCQ0UFT31uDYIjDkV3qhVDhAh/Yspg7VHBojkCQFr3NIF+ScCyZ5CJBQPGuePLiVrXnJq0Si+IK8T0iqX0VyQ56hsrqdjjB1UzsaqtSS1byPC6xWQ6v+T+nI8KDfECQQCYHBUCBH0J1VM5dmdXhJCdCIBy2kw3q3oMKd82J0iKFqikxItu6WVBE3ONOOsqXbhvQGJ6A9BkHx5j6kdOvP6u';
        $data = [
            'mer_no' => $appid,
            'phone' => $mobile,
            'pname' => $username,
            'order_amount' => $amount,
            'goods' => rand(10000000,99999999).sprintf('%.0f', floatval(explode(' ', microtime())[0]) * 1000),
            'notifyUrl' => $notify_url,
            'pageUrl' => $config['redirect_url'],
            'ccy_no' => 'PHP',
            'pemail' => $email,
            'busi_code' => 101202,//菲律宾gcash钱包
            'mer_order_no' => $orderid,
        ];

        $header = [
            'Content-Type:application/json;charset=UTF-8',
        ];
        $data['sign'] = $this->encrypt($data,$key);


        $result = $this->curl_post_content($this->api_url . '/ty/orderPay', json_encode($data), $header);
        save_log('jypay', '提交参数:' . json_encode($data) . ',接口返回信息：' . $result);
        $res = json_decode($result, true);
        if (!isset($res['status'])) {
            $res['message'] = 'Http Request Invalid';
        }
        $returl = '';
        if (is_array($res)) {
            if ($res['status'] == 'SUCCESS') {
                $returl = $res['order_data'];
            }
        }

        return $returl;
    }

    //回调地址 /client/Pay_Notify/templatepay_notify
    public function notify($params, $header = [], $channel, $logname)
    {
        try {
            //参数
            $sign = $params['sign'] ?? '';
            $data['json'] = json_encode($params);
            $data['orderid'] = $params['mer_order_no'] ?? '';   //平台内部订单号
            $data['realmoney'] = $params['order_amount'] ?? '';
            $data['transactionId'] = $params['transaction_id'] ?? '';    //三方订单号
            $data['code'] = $params['code'] ?? '';
            $data['status'] = $params['status'] ?? '' == 'SUCCESS' ? 1 : 0;
            //sign认证
            unset($params['sign']);

            $order = (new UserDB())->getTableRow('T_UserChannelPayOrder', ['OrderId' => $data['orderid']], '*');
            $order['ChannelID'] = $order['ChannelID'] ?? 0;
            // if ($order['ChannelID'] != $channel['ChannelId']) {
            $channel_all = (new MasterDB())->getTableRow('T_GamePayChannel', ['channelID' => $order['ChannelID']], '*');
            if (empty($channel_all)) {
                exit('fail:Channel Not Exist');
            }
            $channel = json_decode($channel_all['MerchantDetail'], true);
            $channel['ChannelId'] = $channel_all['ChannelId'];
            // }

            $checksign = $this->createSign($params, $channel['secret']);
            (new \paynotify\PayNotify('SUCCESS'))->notify($data, $sign, $checksign, $channel, $logname);
        } catch (Exception $ex) {
            save_log($logname, 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            exit('fail');
        }
    }

    //回调地址 /client/Pay_Notify/templatepay_outnotify
    public function outnotify($params, $header = [], $channel, $logname)
    {
        try {
            //参数
            $sign = $params['sign'] ?? '';
            $data['json'] = json_encode($params);
            $data['orderid'] = $params['mer_order_no'] ?? '';
            $data['realmoney'] = $params['order_amount'] ?? '';
            $data['transactionId'] = $params['transaction_id'] ?? '';
            $data['code'] = $params['status'];
            switch ($data['code']) {
                case 'SUCCESS':
                    //成功
                    $data['status'] = 1;
                    break;
                case 'FAIL':
                    //失败
                    $data['status'] = 2;
                    break;
            }
            unset($params['sign']);

            $order = (new BankDB())->getTableRow('UserDrawBack', ['OrderNo' => $data['orderid']], '*');
            $order['ChannelId'] = $order['ChannelId'] ?? 0;

            $channel_all = (new MasterDB())->getTableRow('T_GamePayChannel', ['channelID' => $order['ChannelId']], '*');

            if (empty($channel_all)) {
                exit('fail:Channel Not Exist');
            }
            $channel = json_decode($channel_all['MerchantDetail'], true);
            $channel['ChannelId'] = $channel_all['ChannelId'];

            $checksign = $this->createSign($params, $channel['secret']);

            (new \paynotify\PayNotify('SUCCESS'))->outnotify($data, $sign, $checksign, $channel, $logname);
        } catch (Exception $ex) {
            save_log($logname, 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            exit('fail');
        }
    }


    private function createSign($data, $Md5key)
    {
        ksort($data);
        $md5str = '';
        foreach ($data as $key => $val) {
            if (trim($val) !== '') {
                $md5str = $md5str . $key . '=' . $val . '&';
            }
        }
        $str = $md5str . 'key=' . $Md5key;
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

    function encrypt($data,$mch_private_key){
//        global $mch_private_key;
        ksort($data);
        $str = '';
        foreach ($data as $k => $v){
            if(!empty($v)){
                $str .=(string) $k.'='.$v.'&';
            }
        }
        $str = rtrim($str,'&');
        $encrypted = '';
        //替换成自己的私钥
        $pem = chunk_split($mch_private_key, 64, "\n");
        $pem = "-----BEGIN PRIVATE KEY-----\n" . $pem . "-----END PRIVATE KEY-----\n";
        $private_key = openssl_pkey_get_private($pem);
        $crypto = '';
        foreach (str_split($str, 117) as $chunk) {
            openssl_private_encrypt($chunk, $encryptData, $private_key);
            $crypto .= $encryptData;
        }
        $encrypted = base64_encode($crypto);
        return str_replace(array('+','/','='),array('-','_',''),$encrypted);
    }
}
