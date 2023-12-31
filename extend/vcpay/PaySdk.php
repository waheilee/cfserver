<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

//墨西哥
namespace vcpay;

use Utility\Utility;
use think\facade\Cache;
use app\model\MasterDB;
use app\model\UserDB;
use app\model\BankDB;

class PaySdk
{


    private $api_url = '';

    private $appid = '';
    private $secret = '';



    public function __construct()
    {
        $this->api_url = '';
        $this->secret = '';
    }


    public function pay($param, $config = [])
    {


        if (!empty($config['app_id'])) {
            $this->appid = $config['app_id'];
        }
        if (!empty($config['secret'])) {
            $this->secret = $config['secret'];
        }
        if (!empty($config['api_url'])) {
            $this->api_url = $config['api_url'];
        }

        $appid = $this->appid;
        $orderId = trim($param['orderid']);
        $amount = sprintf('%.2f', $param['amount']);
        $notifyUrl = trim($config['notify_url']);



        $amount = (int)$amount * 100;
        $data = [
            'app_id' => $appid,
            'nonce_str' => rand(10000000, 99999999) . sprintf('%.0f', floatval(explode(' ', microtime())[0]) * 1000),
            'trade_type' => $config['code'],
            'order_amount' => $amount,
            'out_trade_no' => $orderId,
            'notify_url' => $notifyUrl,
            'back_url' => $config['redirect_url']
        ];

        $header = [
            'Content-Type:application/json;charset=UTF-8',
        ];
        $data['sign'] = $this->createSign($data, $this->secret);
        $beginTime = time();
        save_log('vcpay', '开始请求api:'.$beginTime );
        $result = $this->curl_post_content($this->api_url . '/pay/save', json_encode($data), $header);
        $endTime = time();
        $time = $beginTime - $endTime;
        save_log('vcpay', '结束请求api:'.$time);
        save_log('vcpay', '提交参数:' . json_encode($data) . ',接口返回信息：' . $result . '-----请求路径：' . $this->api_url);
        $res = json_decode($result, true);
        if (!isset($res['code'])) {
            $res['message'] = 'Http Request Invalid';
        }
        $returl = '';
        if (is_array($res)) {
            if ($res['code'] == '200') {
                $returl = $res['pay_url'];
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
            $data['orderid'] = $params['out_trade_no'] ?? '';   //平台内部订单号
            $data['realmoney'] = $params['order_amount'] ?? '';
            $data['transactionId'] = $params['trade_no'] ?? '';    //三方订单号
            $data['code'] = $params['trade_state'];
            if ($params['trade_state'] == 0 && $params['code'] == '200' && $params['msg'] == 'SUCCESS') {
                $data['status'] = 1;
            } else {
                $data['status'] = $params['trade_state'] ?? '' == '1' ? 1 : 0;

            }

            $data['realmoney'] = $data['realmoney'] / 100;
            //sign认证
            unset($params['sign']);
            unset($params['code']);
            unset($params['msg']);
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
            (new \paynotify\PayNotify('success'))->notify($data, $sign, $checksign, $channel, $logname);
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
            $data['orderid'] = $params['out_trade_no'] ?? '';
            $data['realmoney'] = $params['order_amount'] ?? '';
            $data['transactionId'] = $params['trade_no'] ?? '';
            $data['code'] = $params['trade_state'];
            $data['realmoney'] = $data['realmoney'] / 100;
            switch ($data['code']) {
                case 1:
                    //成功
                    $data['status'] = 1;
                    break;
                case 2:
                    //失败
                    $data['status'] = 2;
                    break;
                case 3:
                    //处理中
                    $data['status'] = 3;
                    break;
                default:
                    $data['status'] = 0;
                    break;
            }
            unset($params['sign']);
            unset($params['code']);
            unset($params['msg']);

            $checksign = $this->createSign($params, $channel['secret']);

            (new \paynotify\PayNotify('success'))->outnotify($data, $sign, $checksign, $channel, $logname);
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
