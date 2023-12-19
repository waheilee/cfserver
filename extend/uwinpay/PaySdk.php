<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace uwinpay;

use app\model\GameOC;
use app\model\UserDB;
use Utility\Utility;
use think\facade\Cache;

class PaySdk
{


    public function pay($param, $config = [])
    {
        //{
        //  "account_type": "default",
        //  "amount": "100",
        //  "description": "goodsbuy",
        //  "email": "1111111@qq.com",
        //  "merchant_code": "100001",
        //  "merchant_order_no": "20230330030528927130",
        //  "mobile": "1100000",
        //  "name": "test",
        //  "notify_url": "https://xxxxxx.com/notify",
        //  "order_time": 1680116728,
        //  "page_url": "https://www.baidu.com",
        //  "sign": "eteCOCowOhduMdeUtXTFWdlwv2q/SbdO9p8mcfyxlLt+M6Y6nqiZHPZ9U5ay6e32z4mIUUNLvwaE4GdI00ay3ajPYX0T57ijKjCS8bZmaiMxtMyGO1FKlDYXIQX/TPVPSOM1dYx0BdsWBLx3Tai+uoOqDOLpsXMdIjUjTzrj7mgmp/O+g/L1t2W+BmurhFTF+7W5DXFrHOJTorrKaTVq4IkZag/u4bk9dv9RhumIVBD7QWfao5rRzPVDYlCHsPJ69RdqPLR8anO331DuU7ZzRguZZwhZkXESla1KRg8mTKff4aaMqn/H5paIEqKNev4JgY7f3gfkhQuZ7BV4p0Yyqw=="
        //}

        $firstname = 'pay';
        $lastName = 'honey';

        $accountType = 'default';
        $amount = $param['amount'];
        $description = 'goodsbuy';
        $mobile = rand(6, 9) . rand(100000000, 999999999);
        $email = $mobile . '@gmail.com';
        $merchantCode = $config['merchant'] ?? '';
        $merchantOrderNo = trim($param['orderid']);
        $name = $firstname . $lastName;
        $notifyUrl = $config['notify_url'] ?? '';
        $orderTime = time();
        $pageUrl = $config['redirect_url'] ?? '';
        $apiUrl = $config['api_url'] ?? 'https://br-api.uwinpay.com';

        $privateKey = $config['private_key'] ?? $this->getDefaultPrivateKey();

        $data = [
            "account_type" => $accountType,
            "amount" => $amount,
            "description" => $description,
            "email" => $email,
            "merchant_code" => $merchantCode,
            "merchant_order_no" => $merchantOrderNo,
            "mobile" => $mobile,
            "name" => $name,
            "notify_url" => $notifyUrl,
            "order_time" => $orderTime,
            "page_url" => $pageUrl,
        ];
        $dataStr = $this->ascSort($data);
        $sign = $this->sign($dataStr, $privateKey);
        $data['sign'] = $sign;
        $postData = json_encode($data);

        $result = $this->curlPostContent($apiUrl . '/pay', $postData);

        save_log('uwinpay', '提交参数:' . json_encode($data) . ',接口返回信息：' . $result);
        $res = json_decode($result, true);
        if (!isset($res['code']) || $res['code'] != "200") {
            $res['message'] = 'Http Request Invalid';
        }
        $paymentUrl = '';
        if (is_array($res)) {
            if ($res['code'] == '200')
                $paymentUrl = $res['data']['pay_link'];
        }

        return $paymentUrl;
    }


    /**
     * @Notes:生成 sha256WithRSA 签名
     * 提示：SPKI（subject public key identifier，主题公钥标识符）
     * @param null $data 待签名内容
     * @param string $extra 私钥数据（如果为单行，内容需要去掉RSA的标识符）
     * @return string               签名串
     */
    private function sign($data, string $extra): string
    {
        // 私钥
        $privateKeyBase64 = "-----BEGIN RSA PRIVATE KEY-----\n";
        $privateKeyBase64 .= wordwrap($extra, 64, "\n", true);
        $privateKeyBase64 .= "\n-----END RSA PRIVATE KEY-----\n";
        // 签名
        $merchantPrivateKey = openssl_get_privatekey($privateKeyBase64);
        openssl_sign($data, $signature, $merchantPrivateKey, OPENSSL_ALGO_SHA256);
        $encryptedData = base64_encode($signature);
        openssl_free_key($merchantPrivateKey);
        return $encryptedData;
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
                    if ($val == ''){
                        continue;
                    }
                    $str .= $k . '=' . $val . '&';
                }
                return rtrim($str, '&');
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
        return "MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQC2Fr43Dj/+CEmCJKgN81uUS3vyUp4BRI3Vzi2ZZlOCwmWuigfHLHW2K49qoKy7TOB84l/rj3NvU6vCioMLl2OK88nfXCmfQQCIu7ZHStNh1RglgXhP/f4L/rq0EjNulK4qD4Us3FBO+7/s0OfYXncd/RI8UAm1ptGf5V4uqAkaxhZr806aed4KwpXFZU4iReFJYT2XXO8CZKfQN2Uh14jr1b79r0bOAHEuk5+EhQgT9KjtufMJjnQMoXTpMcxzfA0qkp4X2Qc61WnwT4l4k4XpYF+erMvO5rays7VwxLgHI+QThm1tCi0gkff3o1/z/ce3XqpuyuHdwRfiuF1gfw7XAgMBAAECggEBAIepXCByqnSeQf4HR3nVVOagcoDw0q2JIM8pZEnEtfVW1iD6z56x3iVSQPClMuv888e3dNVwtAU+ZlpzjfzF1rEAvud9p7jx2e8FQ2HMOr7J38qZskSOrIbNSta8NLtvZG8LzyHEJsUhxTUv03wdrUuXb82lqAZBei5R2iCSqu3ZY187ZfjCJiNCICRWQaS0wubEnXXQa88cWF3ftb2aCqegPzwD0Ko2zaCC3367w/Yk4zJsrTxVzlyM6PZlcaJdqSg/6b6jH10ngDYFxxo+AQ4bagyRVWdQ84K8Fn0q9IjhKVtp7+82YzvGTpAa4dDK4e2/EGfUxXRL7nLxUuVca7ECgYEAzWKwi0BY41/d6E+EKTnCQRXPVACVL8a1vqf1eQ5laSHkhvjS8OgAKYgu2rbwxvz1B0vHUO5vNS5cdqOLgnLf0mHH9fpZq4TKdOjKlA7xvXgVD5Jjm9jLgSK7VVBNBKp7LnCh2aCuVfjv7Ydho162OcWQVgzJzYaxryw/TvT3GbkCgYEA4vZRrFGZC1/206XnPJSib4sq1Xgmudct6isCeogqp830I9D7Hm69yKL6UfECjDDpBupOnkNEeku4kTimaNUMxJTT79TTMEbRiKy9+xnmrgBjkjxYskBzPtAMCnMZpeNn3jrWt9/kedgiwTJlxFgvNRqWCOOwXKljljT7gMbbdQ8CgYEAnEsNreo5uk2pwK9CE10wxfai33nSDZlZlMybsJOT+H0iOtP/MfRaq0BG54lvkP3OOM8hziSj3AR7uIycDZj9WkuurzDkK/HRX0YHYsQ8kcJfxInR4zcHJi4YAMQq1/Ij6yMrB0GPaT0W19q+ImRgp3YAcHsq1ow5iuRRCPTBVYECgYAgvYO+pe6781X55h7bYF2mVZ8SOEjt2hqngxjScD4nAtDLMeRn2XXLMaeGlovViWC0PKymq/F+6tlvKYrn6IP0/7srB7qHZk/ntXOae3wJccjrWYU6AY4ea4ixITV79rgPGNHMqKGe6gzpbcm8bzQwJuup0J6qX00cZ/w38XfLBQKBgQCRGpCY37e0rm7aFfGCDjRnAhMfHCRNGW6PUaTvZinNmhf5d+PwFEbCcESNQjzz2NSExhiNGHeGh5gTE4jWN9seaxD+K7SKPjFBEy6fUu/+eqveIXCMWkbxaizeFrvf9H4Y4PZtgCQUZcMMnuxfZXfM/8SSm4mzxsOG9ruaMx7KMQ==";
    }

    /**
     * 文档给的默认公钥
     * @return string
     */
    private function getDefaultPublicKey(): string
    {
        return "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAroupTD8Acdbs0AwPWvAnYziQRRJJ4/gLOXgoDIDOHpqDDhqPYZ6GVVjGEdwqGwc2NgE51w+aU47QYM/4T/tgaIcF+TEmySYOy3/dVcvhh23GPi7oZMsHTTWokfTFPhvXfxEcLd8bRHdJMU2JoEw9qy+fJiZSzifxzBpZpXRuEn644ti+v/JfTQo19TAL4ROGRDkfRjK/tA8S2Y4C1CgaxOJodG/+d0bVf/0VCubkcPrR2TlLI3tFSIKQx5NqKpD/AGc0e0OiryMbv2utIEgoRDRJfM5Jy9Cv2bYPxxyPtj4M081y79DeNw8OunKrHJamYU7yjJ+ft6UzfDqc/7srXQIDAQAB";
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
            $data['json'] = json_encode($params);
            unset($params['sign']);
            $dataStr = $this->ascSort($params);
            $checkSign = $this->verify($dataStr, $sign, $publicKey);
            $data['orderid'] = $merchantOrderNo;   //平台内部订单号
            $data['transactionId'] = $orderNo;    //三方订单号
            $data['code'] = $paymentStatus;
            $data['status'] = $paymentStatus ?? '' == 'SUCCESS' ? 1 : 0;


            save_log('uwinpay', '验签结果:' . json_encode($checkSign));
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
                    'Parameter' => $data['json'],
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
            $data['json'] = json_encode($params);
            unset($params['sign']);
            $dataStr = $this->ascSort($params);
            $checkSign = $this->verify($dataStr, $sign, $publicKey);
            $data['realmoney'] = $transferAmount;   //订单金额
            $data['orderid'] = $merchantOrderNo;   //平台内部订单号
            $data['transactionId'] = $orderNo;    //三方订单号
            $data['code'] = $transferStatus;
            $data['status'] = $transferStatus ?? '' == 'SUCCESS' ? 1 : 0;

            save_log('uwinpay', '订单状态:----' . $data['status']);
            if ($checkSign) {
                $sign = 1;
                $checkSign = 1;
            } else {
                $text = "sign error"; //签名失败 Signature failed
                $errorData = [
                    'OrderId' => $data['orderid'],
                    'Controller' => 'Notify',
                    'Method' => __METHOD__,
                    'Parameter' => $data['json'],
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