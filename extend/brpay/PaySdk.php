<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace brpay;

use app\model\GameOC;
use app\model\UserDB;

class PaySdk
{


    public function pay($param, $config = [])
    {
        //{
        //pay_memberid      商户号
        //pay_orderid       订单号
        //pay_applydate     提交时间
        //pay_notifyurl     服务端通知
        //pay_amount        订单金额
        //pay_callbackurl     返回地址
        //pay_md5sign       MD5 签名
        //pay_attach        附加字段
        //pay_username      用户姓名
        //pay_useremail     用户邮箱
        //pay_userphone     用户电话
        //pay_type          支付类型
        //pay_value         CPF或者CNPJ的值
        //}
        $firstname = 'pay';
        $lastName = 'honey';

        $payMemberId = $config['merchant'] ?? '';
        $payOrderId = trim($param['orderid']);
        $payApplyDate = date('Y-m-d H:i:s');
        $payNotifyUrl = $config['notify_url'] ?? '';
        $payAmount = $param['amount'];
        $payCallbackUrl = $config['redirect_url'] ?? '';
        $payUserName = $firstname . $lastName;
        $payUserPhone = rand(6, 9) . rand(100000000, 999999999);;
        $payUserEmail = $payUserPhone . '@gmail.com';
        $payType = 'CPF';
        $payValue = '00000000000';
        $apiUrl = $config['api_url'] ?? 'https://pay.ycfshopping.com';
        $secretKey = $config['secret'] ?? '';

        $data = [
            "pay_memberid" => $payMemberId,
            "pay_orderid" => $payOrderId,
            "pay_applydate" => $payApplyDate,
            "pay_notifyurl" => $payNotifyUrl,
            "pay_amount" => $payAmount,
            "pay_callbackurl" => $payCallbackUrl,
        ];

        $sign = $this->createSign($data, $secretKey);
        $data['pay_md5sign'] = $sign;
        $data['pay_username'] = $payUserName;
        $data['pay_userphone'] = $payUserPhone;
        $data['pay_useremail'] = $payUserEmail;
        $data['pay_type'] = $payType;
        $data['pay_value'] = $payValue;
        $postData = json_encode($data);
        $header = [
            'Content-Type:application/json;charset=UTF-8',
        ];

        $result = $this->curlPostContent($apiUrl . '/api/pay/transactions/get', $postData, $header);

        $res = json_decode($result, true);
        $paymentUrl = '';
        if (isset($res)) {
            if ($res['code'] == 1) {
                $paymentUrl = $res['data']['pay_url'];
            } else {
                save_log('brpay', '失败订单:' . $payOrderId . '订单状态：' . $res['code'] . '失败消息：' . $res['msg']);
            }
        } else {
            save_log('brpay', '无同步失败订单:' . $payOrderId);
        }

        return $paymentUrl;
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


    private function curlPostContent($url, $postData = null, $header = [])
    {
        $ch = curl_init();
        if (substr_count($url, 'https://') > 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!empty($postData)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
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


    /**
     * 支付回调
     * @return void
     */
    public function notify($params, $header, $channel, $logName)
    {
        try {
            $secretKey = $channel['secret'] ?? '';
            $orderId = $params['orderid'];
            $returnCode = $params['returncode'];
            $sign = $params['sign'];

            $data['json'] = json_encode($params);
            unset($params['sign']);

            $data['orderid'] = $orderId;   //平台内部订单号
            $data['transactionId'] = $params;    //三方订单号
            $data['code'] = $returnCode;
            $data['status'] = $returnCode ?? '';

            $userDB = new UserDB();
            $order = $userDB->getTableObject('T_UserChannelPayOrder')
                ->where('OrderId', $data['orderid'])
                ->find();
            $data['realmoney'] = $order['RealMoney'];
            $checkSign = $this->createSign($params, $secretKey);
            (new \paynotify\PayNotify('OK'))->notify($data, $sign, $checkSign, $channel, $logName);
        } catch (\Exception $ex) {
            save_log($logName, 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            exit('fail');
        }
    }

    /**
     * 支出回调
     * @return void
     */
    public function outnotify($params, $header, $channel, $logName)
    {

        try {

            $merchantOrderNo = $params['out_trade_no'];
            $orderNo = $params['transaction_id'];
            $status = $params['refCode'];
            $timestamp = $params['success_time'];
            $sign = $params['sign'];

            $data['json'] = json_encode($params);
            unset($params['sign']);

            $data['orderid'] = $merchantOrderNo;   //平台内部订单号
            $data['transactionId'] = $orderNo;    //三方订单号
            $data['code'] = $status;
            $data['status'] = '2';//默认给失败
            if (isset($timestamp) && $status == 1) {
                $data['status'] = '1';//
            }
            save_log('brpay', '回调参数' . json_encode($params));
            save_log('brpay', '订单状态:----' . $data['status']);
            $checkSign = $this->createSign($params, $channel['secret']);
            save_log('brpay', '验签----' . $checkSign);
            (new \paynotify\PayNotify('OK'))->outnotify($data, $sign, $checkSign, $channel, $logName);
        } catch (\Exception $ex) {
            save_log($logName, 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            exit('fail');
        }
    }


}