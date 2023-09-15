<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

//墨西哥
namespace seckkpay;


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
        $timestamp = date('Y-m-d H:i:s');

        /**
         * pay_memberid    商户号    是    是    平台分配商户号
         * pay_orderid    订单号    是    是    订单号唯一, 字符长度20
         * pay_applydate    提交时间    是    是    时间格式：2016-12-26 18:18:18
         * pay_bankcode    通道编码    是    是    参考后续说明，或找客服咨询
         * pay_notifyurl    服务端通知地址    是    是    服务端返回地址.（POST返回数据）
         * pay_callbackurl    页面跳转通知地址    是    是    页面跳转返回地址（POST返回数据）
         * pay_amount    订单金额    是    是    商品金额
         * pay_md5sign    MD5签名    是    否    请看MD5签名字段格式
         * pay_attach    附加字段    否    否    此字段在返回时按原样返回(中文需要url编码)
         * pay_productname    商品名称    是    否
         * pay_productnum    商户品数量    否    否
         * pay_productdesc    商品描述    否    否
         * pay_producturl    商户链接地址    否    否
         */
        $data = [
            'pay_memberid' => $appid,
            'pay_orderid' => $orderid,
            'pay_applydate' => $timestamp,
            'pay_bankcode' => $config['code'],
            'pay_notifyurl' => $notify_url,
            'pay_callbackurl' => trim($config['redirect_url']),
            'pay_amount' => (float)$amount,
            'pay_productname' => 'ID' . $param['roleid'],
        ];

        $header = [
            'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
        ];
        $data['pay_md5sign'] = $this->createSign($data, $this->secret);

        $result = $this->curl_post_content($this->api_url . '/Pay_Index.html', http_build_query($data), $header);
        save_log('seckkpay', '提交参数:' . json_encode($data) . ',接口返回信息：' . $result);
        $res = json_decode($result, true);

        if (!isset($res['status'])) {
            $res['message'] = 'Http Request Invalid';
        }
        $returl = '';
        if (is_array($res)) {
            if ($res['status'] == '1')
                $returl = $res['redirect_url'];
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
            $data['orderid'] = $params['orderid'] ?? '';   //平台内部订单号
            $data['realmoney'] = $params['amount'] ?? '';
            $data['transactionId'] = 'jb' . $params['transaction_id'];    //三方订单号
            $data['code'] = $params['returncode'];
            $data['status'] = $params['returncode'] ?? '' == '00' ? 1 : 0;

            //sign认证
            unset($params['sign']);

            $checksign = $this->createSign($params, $channel['secret']);
            (new \paynotify\PayNotify('OK'))->notify($data, $sign, $checksign, $channel, $logname);
        } catch (\Exception $ex) {
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
            $data['orderid'] = $params['orderid'] ?? '';
            $data['realmoney'] = $params['amount'] ?? '';
            $data['transactionId'] = 'jb' . $params['transaction_id'];
            $data['code'] = $params['returncode'];

            switch ($data['code']) {
                case '00':
                    //成功
                    $data['status'] = 1;
                    break;
                default:
                    $data['status'] = 0;
                    break;
            }
            unset($params['sign']);
            $checksign = $this->createSign($params, $channel['secret']);

            (new \paynotify\PayNotify('OK'))->outnotify($data, $sign, $checksign, $channel, $logname);
        } catch (\Exception $ex) {
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
