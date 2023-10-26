<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

//墨西哥
namespace gtrpay;

use jbpay\Exception;
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

        if (!empty($config['merchant'])) {
            $this->merchant = $config['merchant'];
        }
        if (!empty($config['appid'])) {
            $this->appid = $config['appid'];
        }
        if (!empty($config['secret'])) {
            $this->secret = $config['secret'];
        }
        if (!empty($config['apiurl'])) {
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


        $data = [
            'mchId' => $merchant,
            'passageId' => 101,
            'orderAmount' => (float)$amount,
            'orderNo' => $orderid,
            'notifyUrl' => $notify_url,
            'callBackUrl' => trim($config['redirect_url']),
        ];

        $header = [
            'Content-Type: application/json',
        ];
        $data['sign'] = $this->createSign($data, $this->secret);
        $result = $this->curlPostContent($this->api_url . '/collect/create', json_encode($data), $header);
        save_log('gtrpay', '提交参数:' . json_encode($data) . ',接口返回信息：' . $result);
        $res = json_decode($result, true);
        if (!isset($res) || $res['code'] != 200) {
            $res['message'] = 'Http Request Invalid';
        }
        $returl = '';
        if (is_array($res)) {
            if ($res['code'] == 200)
                $returl = $res['data']['payUrl'];
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
            $data['orderid'] = $params['orderNo'] ?? '';
            $data['realmoney'] = $params['realAmount'] ?? '';
            $data['transactionId'] =  $params['tradeNo'];
            $data['code'] = $params['payStatus'];

            switch ($data['code']) {
                case '0':
                    //支付中
                    $data['status'] = 0;
                    break;
                case '1':
                    //支付成功
                    $data['status'] = 1;
                    break;
                case '2':
                    //支付失败
                    $data['status'] = 2;
                    break;
                default:
                    $data['status'] = 0;
                    break;
            }

            //sign认证
            unset($params['sign']);
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
            $data['orderid'] = $params['orderNo'] ?? '';
            $data['realmoney'] = $params['realAmount'] ?? '';
            $data['transactionId'] =  $params['tradeNo'];
            $data['code'] = $params['payStatus'];

            switch ($data['code']) {
                case '0':
                    //支付中
                    $data['status'] = 0;
                    break;
                case '1':
                    //支付成功
                    $data['status'] = 1;
                    break;
                case '2':
                    //支付失败
                    $data['status'] = 2;
                    break;
                default:
                    $data['status'] = 0;
                    break;
            }
            unset($params['sign']);
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


    private function curlPostContent($url, $data = null, $header = [])
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
