<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace bpay;

use app\model\GameOC;
use app\model\UserDB;
use Utility\Utility;
use think\facade\Cache;

class PaySdk
{


    public function pay($param, $config = [])
    {
        $mobile = rand(6, 9) . rand(100000000, 999999999);
        $email = $mobile . '@gmail.com';
        $firstname = 'pay';
        $lastName = 'honey';

        $privateKey = $config['private_key'] ?? $this->getDefaultPrivateKey();
        $merchantNo = $config['merchant'] ?? '';
        $merchantOrderNo = trim($param['orderid']);
        $countryCode = $config['code'] ?? '';
        $currencyCode = $config['currency'] ?? '';
        $paymentType = $config['payment_type'] ?? '900410282001';
        $paymentAmount = sprintf('%.2f', $param['amount']);
        $goods = "iphone15";
        $notifyUrl = $config['notify_url'] ?? '';
        $apiUrl = $config['api_url'] ?? 'https://api.bpay.tv/api/v2/payment/order/create';
        $extendedParams = "payerFirstName^$firstname|payerLastName^$lastName|payerEmail^$email|payerPhone^$mobile|payerCPF^$mobile";
        $data = [
            'merchantNo' => $merchantNo,
            'merchantOrderNo' => $merchantOrderNo,
            'countryCode' => $countryCode,
            'currencyCode' => $currencyCode,
            'paymentType' => $paymentType,
            'paymentAmount' => $paymentAmount,
            'goods' => $goods,
            'notifyUrl' => $notifyUrl,
            'extendedParams' => $extendedParams,
        ];
        $dataStr = $this->ascSort($data);
        $sign = $this->sign($dataStr, $privateKey);
        $data['sign'] = $sign;
        $postData = json_encode($data);
        $header = [
            'Content-Type: application/json; charset=utf-8',
            'Content-Length:' . strlen($postData),
            'Cache-Control: no-cache',
            'Pragma: no-cache'
        ];
        $result = $this->curlPostContent($apiUrl, $postData, $header);
        save_log('bypay', '提交参数:' . json_encode($data) . ',接口返回信息：' . $result);
        $res = json_decode($result, true);
        if (!isset($res['code']) || $res['code'] != "200") {
            $res['message'] = 'Http Request Invalid';
        }
        $paymentUrl = '';
        if (is_array($res)) {
            if ($res['code'] == '200')
                $paymentUrl = $res['data']['paymentUrl'];
        }

        return $paymentUrl;
    }


    /**
     * 支付加密
     * 验签数组 @param $data
     * 密钥 @param $extra
     * @return string
     */
    private function sign($data, $extra): string
    {
        // 私钥
        $privateKeyBase64 = "-----BEGIN PRIVATE KEY-----\n";
        $privateKeyBase64 .= wordwrap($extra, 64, "\n", true);
        $privateKeyBase64 .= "\n-----END PRIVATE KEY-----\n";
        // 签名
        $merchantPrivateKey = openssl_get_privatekey($privateKeyBase64);
        openssl_sign($data, $signature, $merchantPrivateKey, OPENSSL_ALGO_MD5);
        return base64_encode($signature);
    }

    /**
     * 回调验签
     * @param $data
     * @param $sign
     * @param $publicKey
     * @return false|int
     */
    private function verify($data, $sign, $publicKey)
    {

        $publicKeyBase64 = "-----BEGIN PUBLIC KEY-----\n";
        $publicKeyBase64 .= wordwrap($publicKey, 64, "\n", true);
        $publicKeyBase64 .= "\n-----END PUBLIC KEY-----\n";
        //验证
        $payPublicKey = openssl_get_publickey($publicKeyBase64);

        return openssl_verify($data, base64_decode($sign), $payPublicKey, OPENSSL_ALGO_MD5);
    }

    private function ascSort($data = [])
    {
        if (!empty($data)) {
            $p = ksort($data);
            if ($p) {
                $str = '';
                foreach ($data as $k => $val) {
                    $str .= $k . '=' . $val . '&';
                }
                $strs = rtrim($str, '&');
                return $strs;
            }
        }
        return false;
    }


    private function curlPostContent($url, $postData = null, $header = [])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($curl);
        curl_close($curl);
        return $res;
    }

    /**
     * 文档给的默认私钥
     * @return string
     */
    private function getDefaultPrivateKey(): string
    {
        return "MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBAJnwukGNiyJ5TJaE+xeChBav6PCm8z7CtL49WzlMyWursIeJ6hFc7ikDPUau3SwACtJ8NtrhuTIP8l/8tO0VhCvGOUSDC/7g1SBJsiuQ+j3j9X3bCweVjSM+fBpm7PnLQBOKD1yiGbN7iLVIhqNsQ9JklyPnd8JEnx6I5ykwBh+RAgMBAAECgYBaQu9DHpZVSWBh5WlA2LNQhiaEbK+1vf6yiVFi4KY9rrbcUj5fnei7PX4BYuimMwQldNXJM48eToFkTM1dMj+DbIipoaVtVokqbtKBOIVyIK3SSkQ8+fi/KJWazuuxs+JYmF0JoGOCg4jZmZffrZI6l1QZ6XAwrYq1Af7W47K6fQJBAOGs0ZvZUv5tz2GRtxMFpMjyoy+RoOIPQM6k1P11QWshGPKI5lkzcpCHV9X6EU5LQ0e/u0u0UQ36ywAea4uW0dMCQQCuoD9FTEzYRHDWc/S/klDxLJrjLZ6YAZkDUZvymDTgnfP8MmhrGC+QL8+8yzLBJnBj+upXHiGLab5rz+xVI8aLAkEAp4PHx57G61OJl4w5T+ZljkAFf77ipErcOUfDTiymlaXoxcd27QmyZbQBMDVCeVKGq5CXr7c2X2ElJH5wKBqYvwJAboURRkSiJgY6/B9reYubGujGJp4Kz93C/+y4rHNUlAykDKvClnU6NSFtcumP99riKwT1J6n0RQ3p7MYtpzz7PQJBAK7zWytx/zovntDBcarwjjFvMqwQxqfzJTL691f+hH7gPjTvOqtf2c25jruO6hnwybLukhutNhwSB0gqu45XU5w=";
    }

    /**
     * 文档给的默认公钥
     * @return string
     */
    private function getDefaultPublicKey(): string
    {
        return "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCEq/XP6fFscHpRaAhMHRDR8o6p4luI0i3DolDh29n/FGccK4ibx0lnBLci31JP9mfGsnFqrZxBAvenjwD/gYKNVXtWBZLoN6qbNg1kw/yoD/7iQYbHol2ETdJplMgmK1L/EJXyy3xh3XjKL4i3wQ2jNzAUO5nG8QGTK4/S8tSzQwIDAQAB";
    }


    /**
     * 支付回调
     * @return void
     */
    public function notify($params, $header, $channel, $logName)
    {
//        array (
//            'orderNo' => '6012220116000004',
//            'orderTime' => '2022-01-16 16:05:45',
//            'orderAmount' => '100.00',
//            'countryCode' => 'THA',
//            'sign' => 'Cmkdx85RlWUgHTt27E21GyUg8yT74AW3ZujFghV4KmaSd93LCT4aDz0j/aUWf2jY2ZbMYOqbwnZZ7N63doGJuATdWzmmNSi6gRVnnCCvR5h12syuv8ab+j++NQbE2wc/wicqGF1c0D5eUwrEm414JC+aIq/ESe0/hWJ8wbaq87s=',
//            'paymentTime' => '2022-01-16 16:05:58',
//            'merchantOrderNo' => '1642323938',
//            'paymentAmount' => '100.00',
//            'currencyCode' => 'THB',
//            'paymentStatus' => 'SUCCESS',
//            'returnedParams' => '回传参数',
//            'merchantNo' => '3018220107001',
//        )
        try {
//            $jsonData = json_decode($params, true);
            $publicKey = $channel['public_key'] ?? $this->getDefaultPublicKey();
//            $countryCode = $jsonData['countryCode'];
//            $orderTime = $jsonData['orderTime'];
//            $orderAmount = $jsonData['orderAmount'];
//            $paymentTime = $jsonData['paymentTime'];
//            $paymentAmount = $jsonData['paymentAmount'];
//            $currencyCode = $jsonData['currencyCode'];
//            $returnedParams = $jsonData['returnedParams'];
//            $merchantNo = $jsonData['merchantNo'];
            $paymentStatus = $params['paymentStatus'];
            $merchantOrderNo = $params['merchantOrderNo'] ?? '';
            $orderNo = $params['orderNo'] ?? '';
            $sign = $params['sign'];
            unset($params['sign']);
            $dataStr = $this->ascSort($params);
            $checkSign = $this->verify($dataStr, $sign, $publicKey);
            $data['orderid'] = $merchantOrderNo;   //平台内部订单号
            $data['transactionId'] = $orderNo;    //三方订单号
            $data['code'] = $paymentStatus;
            $data['status'] = $paymentStatus ?? '' == 'SUCCESS' ? 1 : 0;


            save_log('bpay', '验签结果:' . json_encode($checkSign));
            if ($checkSign) {
                $sign = 1;
                $checkSign = 1;
            } else {
                $text = "sign error"; //签名失败 Signature failed
                $gameOC = new GameOC();
                $errorData = [
                    'OrderId' => $data['orderid'],
                    'Controller' => 'Notify',
                    'Method' => __METHOD__,
                    'Parameter' => json_encode($params),
                    'Error' => $text,
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $gameOC->PaynotifyLog()->insert($errorData);
                exit($text);
            }

            $userDB = new UserDB();
            $order = $userDB->getTableObject('T_UserChannelPayOrder')->where('OrderId', $data['orderid'])->find();
            $data['realmoney'] = $order['RealMoney'];
            (new \paynotify\PayNotify('SUCCESS'))->notify($data, $sign, $checkSign, $channel, $logName);
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
//        array (
//            'orderNo' => '7015220116000002',
//            'orderTime' => '2022-01-16 19:51:48',
//            'transferStatus' => 'SUCCESS',
//            'transferTime' => '2022-01-16 22:01:25',
//            'countryCode' => 'THA',
//            'orderAmout' => '15.00',
//            'transferAmount' => '15.00',
//            'sign' => 'V9Rv2UkjP5lwT/EwONd2//kXq8JqdvnDy/mbjhxwI10JV4syw64jdQZrJ1Sm4ts7njSNAbgdlzelkm3iBUqAorqLcoQrCjrgH567A7ZnFbeoSauqxw4bpK2mR9Jvuv/VzpfFgdEoAmp9KIlEAFHzTu4att+7x4jutTvaRV5SUmY=',
//            'merchantOrderNo' => '1642337500',
//            'currencyCode' => 'THB',
//            'merchantNo' => '3018220107001',
//        )
        try {
            $gameOC = new GameOC();
//            $jsonData = json_decode($params, true);
            $publicKey = $channel['public_key'];
//            $merchantNo = $jsonData['merchantNo'];
//            $orderTime = $jsonData['orderTime'];
//            $orderAmout = $jsonData['orderAmout'];
//            $currencyCode = $jsonData['currencyCode'];
//            $countryCode = $jsonData['countryCode'];
//            $transferTime = $jsonData['transferTime'];
            $transferAmount = $params['transferAmount'] ?? '';
            $transferStatus = $params['transferStatus'];
            $orderNo = $params['orderNo'] ?? '';
            $merchantOrderNo = $params['merchantOrderNo'] ?? '';
            $sign = $params['sign'];
            unset($params['sign']);
            $dataStr = $this->ascSort($params);
            $checkSign = $this->verify($dataStr, $sign, $publicKey);
            $data['realmoney'] = $transferAmount;   //订单金额
            $data['orderid'] = $merchantOrderNo;   //平台内部订单号
            $data['transactionId'] = $orderNo;    //三方订单号
            $data['code'] = $transferStatus;
            $data['status'] = $transferStatus ?? '' == 'SUCCESS' ? 1 : 0;

            save_log('bpay', '订单状态:----' . $data['status']);
            if ($checkSign) {
                $sign = 1;
                $checkSign = 1;
            } else {
                $text = "sign error"; //签名失败 Signature failed
                $errorData = [
                    'OrderId' => $data['orderid'],
                    'Controller' => 'Notify',
                    'Method' => __METHOD__,
                    'Parameter' => json_encode($params),
                    'Error' => $text,
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $gameOC->PaynotifyLog()->insert($errorData);
                exit($text);
            }

            (new \paynotify\PayNotify('SUCCESS'))->outnotify($data, $sign, $checkSign, $channel, $logName);
        } catch (\Exception $ex) {
            save_log($logName, 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            exit('fail');
        }
    }
}