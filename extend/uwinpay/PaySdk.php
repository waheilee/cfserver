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
        $header = [
            'Content-Type:application/json;charset=UTF-8',
        ];
        $result = $this->curlPostContent($apiUrl . '/pay', $postData,$header);

        save_log('uwinpay', '提交参数:' . $postData . ',接口返回信息：' . $result);
        $res = json_decode($result, true);
        if (!isset($res['code']) || $res['code'] != "200") {
            $res['message'] = 'Http Request Invalid';
        }elseif($res['code'] != "400"){
            $res['message'] = '缺少请求参数';
        }elseif($res['code'] != "401"){
            $res['message'] = '签名错误';
        }elseif($res['code'] != "406"){
            $res['message'] = '订单号重复';
        }elseif($res['code'] != "407"){
            $res['message'] = '订单金额超出或低于限额';
        }elseif($res['code'] != "408"){
            $res['message'] = '账户余额不足';
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

        return openssl_verify($data, base64_decode($sign), $payPublicKey, OPENSSL_ALGO_SHA256);
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
//  {
//  "amount": "100",
//  "error_message": "",
//  "merchant_code": "100000",
//  "merchant_order_no": "20230330001654904935",
//  "order_no": "PO1641112265354645504",
//  "status": 2,
//  "timestamp": 1680106820,
//  "sign": "aUS5yirj6HlX8HGxbxbmX4Ufye5rGZi+ifTnZXVpLadaYtxkxuqtuAFPEke4vkvXlUiwmpYuy5/oP+1WFp6+4u3cr6dy7NvwRUWko/QkpkhlkoeP2pMO7XipOkfXkH1zR9uJ85EWfWRBSdEvE0N9ccAKeF9d58ykuREnmcYELcbUyYKqZcw//x9uKUL3SRyRDpO/rxQH/QJJqsWzuHh41qg2ZsyW3BecXO3muNfScd0RwXlD9jodSA4Ie0OUW/6VdK9DLaVBv4w4gu3ESNz+3AesPMvrD/brG9Cq78g91cYErGFag0rxfMFpuN+znmh/AtVmXfZRYPeXj7sVPmYqHw=="
//  }
        //-1 支付失败
        //0	待支付
        //2	支付成功
        //3	订单异常（只有代收有，比如用户支付金额与实际不符）
        try {
            $publicKey = $channel['public_key'] ?? $this->getDefaultPublicKey();
            $amount = $params['amount'];
            $errorMessage = $params['error_message'];
            $errorMessage = $params['merchant_code'];
            $merchantOrderNo = $params['merchant_order_no'];
            $orderNo = $params['order_no'];
            $status = $params['status'];
            $timestamp = $params['timestamp'];
            $sign = $params['sign'];

            $data['json'] = json_encode($params);
            unset($params['sign']);
            $dataStr = $this->ascSort($params);
            $checkSign = $this->verify($dataStr, $sign, $publicKey);
            $data['orderid'] = $merchantOrderNo;   //平台内部订单号
            $data['transactionId'] = $orderNo;    //三方订单号
            $data['code'] = $status;
            $data['status'] = 0;
            if ($status == 2){
                $data['status'] = 1;
            }
            save_log('uwinpay', '公钥:' . json_encode($publicKey));
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
            (new \paynotify\PayNotify('ok'))->notify($data, $sign, $checkSign, $channel, $logName);
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
//{
//  "amount": "100",
//  "error_message": "",
//  "merchant_code": "100000",
//  "merchant_order_no": "20230330001654904935",
//  "order_no": "PO1641112265354645504",
//  "status": 2,
//  "timestamp": 1680106820,
//  "sign": "aUS5yirj6HlX8HGxbxbmX4Ufye5rGZi+ifTnZXVpLadaYtxkxuqtuAFPEke4vkvXlUiwmpYuy5/oP+1WFp6+4u3cr6dy7NvwRUWko/QkpkhlkoeP2pMO7XipOkfXkH1zR9uJ85EWfWRBSdEvE0N9ccAKeF9d58ykuREnmcYELcbUyYKqZcw//x9uKUL3SRyRDpO/rxQH/QJJqsWzuHh41qg2ZsyW3BecXO3muNfScd0RwXlD9jodSA4Ie0OUW/6VdK9DLaVBv4w4gu3ESNz+3AesPMvrD/brG9Cq78g91cYErGFag0rxfMFpuN+znmh/AtVmXfZRYPeXj7sVPmYqHw=="
//}
        try {
            $gameOC = new GameOC();
            $publicKey = $channel['public_key'] ?? $this->getDefaultPublicKey();
            $amount = $params['amount'];
            $errorMessage = $params['error_message'];
            $errorMessage = $params['merchant_code'];
            $merchantOrderNo = $params['merchant_order_no'];
            $orderNo = $params['order_no'];
            $status = $params['status'];
            $timestamp = $params['timestamp'];
            $sign = $params['sign'];

            $data['json'] = json_encode($params);
            unset($params['sign']);
            $dataStr = $this->ascSort($params);
            $checkSign = $this->verify($dataStr, $sign, $publicKey);
            $data['orderid'] = $merchantOrderNo;   //平台内部订单号
            $data['transactionId'] = $orderNo;    //三方订单号
            $data['code'] = $status;
            $data['status'] = 0;
            if ($status == 2){
                $data['status'] = 1;
            }

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

            (new \paynotify\PayNotify('ok'))->outnotify($data, $sign, $checkSign, $channel, $logName);
        } catch (\Exception $ex) {
            save_log($logName, 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            exit('fail');
        }
    }
}