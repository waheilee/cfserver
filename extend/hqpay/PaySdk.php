<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

//墨西哥
namespace hqpay;


use app\model\BankDB;
use app\model\GameOC;
use app\model\UserDB;
use EllipticCurve\Ecdsa;
use EllipticCurve\PrivateKey;
use EllipticCurve\PublicKey;
use EllipticCurve\Signature;

class PaySdk
{

    private $appid;

    private $apiUrl;
    private $privateKey;


    public function __construct()
    {
        $this->privateKey = '';
        $this->appid = '';
        $this->apiUrl = 'https://doc.mkcpay.com/api/pay/v1/mkcPay/createBrCode';
    }


    public function pay($param, $config = [])
    {

        if (!empty($config['appid'])) {
            $this->appid = $config['appid'];
        }

        if (!empty($config['apiurl'])) {
            $this->apiUrl = $config['apiurl'];
        }
        if (!empty($config['private_key'])) {
            $this->privateKey = $config['private_key'];
        }


        $apiUrl = $this->apiUrl;
        $appid = $this->appid;
        $orderId = (string)$param['orderid'];
        $amount = (int)$param['amount'] * 100;

        $data = [
            'amount' => $amount,
            'externalOrderNo' => $orderId,
        ];

        $dataMd5 = $this->ksrotArrayMd5($data);
        $sign = $this->encry($dataMd5,$this->privateKey);
        $header = [
            'Content-Type: application/json; charset=utf-8',
            'sign:' . $sign,
            'appKey:' . $appid,
        ];
        save_log('mkcpay', '提交参数:' . json_encode($data));
        //{
        //    "result": {
        //        "orderNo": "r79903742490648129536",
        //        "pictureUrl": "https://payment.kirinpays.com/upipay/home?orderId=20230925065106G01P7586942666&amount=20&returnUrl=https://payment.kirinpays.com/upipay/success&qrcode=00020126920014br.gov.bcb.pix2570qrcodes.sulcredi.coop.br/v2/v3/at/e6f382a0-0950-44d5-b03c-cc6d78dc32ff5204000053039865802BR5912VIATECH%20LTDA6009SAO%20PAULO62070503***63040B3B",
        //        "createTime": 1695635466892,
        //        "code": "00020126920014br.gov.bcb.pix2570qrcodes.sulcredi.coop.br/v2/v3/at/e6f382a0-0950-44d5-b03c-cc6d78dc32ff5204000053039865802BR5912VIATECH LTDA6009SAO PAULO62070503***63040B3B"
        //    },
        //    "success": true,
        //    "message": "success",
        //    "code": 200
        //}

        $result = $this->curl_post_content($apiUrl, json_encode($data), $header);

        $res = json_decode($result, true);
        $resultData = '';
        if (isset($res['result']) && $res['code'] == 200) {
            $resultData = $res['result']['pictureUrl'];
        }
        return $resultData;
    }

    //回调地址 /client/Pay_Notify/templatepay_notify
    public function notify($params, $header, $channel, $logname)
    {

        //{
        //"createTime":1690352033545,
        //"event":"transfer",
        //"log":{
        //"accountNumber":"xx",
        //"actualAmount":100,
        //"externalOrderNo":"xxxx",//商户订单号
        //"fee":15,
        //"name":"xx",
        //"orderNo":"xx",//平台订单号
        //"status":"success", //created-订单已创建 success-成功 failed-失败 canceled-取
        //消
        //"taxId":"xxx"
        //}
        //}

        try {

            //参数
            $publicKeyConfig = $channel['public_key'];
            $sign = $header['digital-signature'] ?? '';
            $checkSign = $this->verify($params, $sign, $publicKeyConfig);
            $data['json'] = $params;
            $json = json_decode($params, 1);
            $log = $json['log'];
            $data['orderid'] = $log['externalOrderNo'] ?? '';   //平台内部订单号
            $data['transactionId'] = $log['orderNo'] ?? '';    //三方订单号
            $data['code'] = $log['status'];
            $data['status'] = $log['status'] ?? '' == 'success' ? 1 : 0;

            if ($checkSign) {
                $sign = 1;
                $checkSign = 1;
            } else {
                $text = "sign error"; //签名失败 Signature failed
                $gameoc = new GameOC();
                $errorData = [
                    'OrderId' => $data['orderid'],
                    'Controller' => 'Notify',
                    'Method' => __METHOD__,
                    'Parameter' => $data['json'],
                    'Error' => $text,
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $gameoc->PaynotifyLog()->insert($errorData);
                exit($text);
            }

            $userDB = new UserDB();
            $order = $userDB->getTableObject('T_UserChannelPayOrder')->where('OrderId', $data['orderid'])->find();
            $data['realmoney'] = $order['RealMoney'];
            (new \paynotify\PayNotify('OK'))->notify($data, $sign, $checkSign, $channel, $logname);
        } catch (\Exception $ex) {
            save_log($logname, 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            exit('fail');
        }
    }

    //回调地址 /client/Pay_Notify/templatepay_outnotify
    public function outnotify($params, $header, $channel, $logname)
    {

        try {
            $gameoc = new GameOC();
            //参数
            $publicKeyConfig = $channel['public_key'];
            $sign = $header['digital-signature'] ?? '';
            $checkSign = $this->verify($params, $sign, $publicKeyConfig);
            $data['json'] = $params;
            $json = json_decode($params, 1);
            $log = $json['log'];
            $data['realmoney'] = $log['actualAmount'] ?? '';   //订单金额
            $data['orderid'] = $log['externalOrderNo'] ?? '';   //平台内部订单号
            $data['transactionId'] = $log['orderNo'] ?? '';    //三方订单号
            $data['code'] = $log['status'];
            $data['status'] = $log['status'] ?? '' == 'success' ? 1 : 0;
            if ($data['code'] == "failed" || $data['code'] == "canceled") {
                $data['status'] = '2';
            }
            save_log('mkcpay', '订单状态:----' . $data['status']);
            //            $checkSign = $this->verify($data['json'], $sign);
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
                $gameoc->PaynotifyLog()->insert($errorData);
                exit($text);
            }

            (new \paynotify\PayNotify('OK'))->outnotify($data, $sign, $checkSign, $channel, $logname);
        } catch (\Exception $ex) {
            save_log($logname, 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            exit('fail');
        }
    }


    private function ksrotArrayMd5($data)
    {
        ksort($data);
        $str = json_encode($data);
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


    /**
     * 加密
     */
    public function encry($data,$privateKeyConfig)
    {
        //$key = 'MHUCAQEEIRgDFg6d7/rz9qBOiFnTyLCT4p6yw3fhQR+qKmsJpTMjxKAHBgUrgQQACqFEA0IABL7v4UTuEF9d24QPZJJVv7d+QEJXdd9JfmvFKn3ofIsqRcyPkIDK3VTrl6qEa86YAT5ZN05puDj2J689L/6wIgo=';

        $privateKey = "-----BEGIN EC PRIVATE KEY-----\n";
        $privateKey .= wordwrap($privateKeyConfig, 64, "\n", true);
        $privateKey .= "\n-----END EC PRIVATE KEY-----\n";

# Generate privateKey from PEM string
        $privateKey = PrivateKey::fromPem($privateKey);

        $signature = Ecdsa::sign($data, $privateKey);

// Generate Signature in base64. This result can be sent to Stark Bank in header as Digital-Signature parameter
        return $signature->toBase64();
    }

    public function verify($data, $sig, $publicKeyConfig)
    {
//        $key = 'MFYwEAYHKoZIzj0CAQYFK4EEAAoDQgAEvu/hRO4QX13bhA9kklW/t35AQld130l+a8Uqfeh8iypFzI+QgMrdVOuXqoRrzpgBPlk3Tmm4OPYnrz0v/rAiCg==';

        $publicKeyPem = "-----BEGIN PUBLIC KEY-----\n";
        $publicKeyPem .= wordwrap($publicKeyConfig, 64, "\n", true);
        $publicKeyPem .= "\n-----END PUBLIC KEY-----\n";
        $publicKey = PublicKey::fromPem($publicKeyPem);
        $signature = Signature::fromBase64($sig);
        return Ecdsa::verify(trim($data), $signature, $publicKey);
    }
}
