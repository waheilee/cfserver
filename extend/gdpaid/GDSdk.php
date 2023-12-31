<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace gdpaid;

use Utility\Utility;
use think\facade\Cache;

class GDSdk
{

    private $api_url = '';
    private $notify_url = '';
    private $appid = '';
    private $secret = '';
    public function __construct()
    {

        $this->api_url = 'https://gd-api.gdpaid.com';
        $this->appid = 'rummyfate';
        $this->secret = 'FsIZ2KNEN5MruVWoPQLI';
    }

    public function makeSignature(&$payload)
    {
        ksort($payload);
        $un_hashed_str = "";
        foreach ($payload as $item) {
            $un_hashed_str = $un_hashed_str . $item;
        }
        return base64_encode(hash_hmac("sha1", $un_hashed_str, $this->secret, $raw_output = TRUE));
    }

    public function httpRequest($url, $method = "POST", $payload = null)
    {
// post http request
        $curl = curl_init();
        if (substr_count($url, 'https://') > 0) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json; charset=utf-8',
                'Content-Length: ' . strlen($payload))
        );
        ob_start();
        curl_exec($curl);
        $return_content = ob_get_contents();
        ob_end_clean();

        $return_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        return $return_content;
    }


    public function pay($param, $config = [])
    {
        if (isset($config['appid']) && !empty($config['appid'])) {
            $this->merchant = $config['appid'];
        }
        if (isset($config['secret']) && !empty($config['secret'])) {
            $this->secretkey = $config['secret'];
        }
        if (isset($config['apiurl']) && !empty($config['apiurl'])) {
            $this->api_url = $config['apiurl'];
        }


        $url = $this->api_url . "/deposit";
        $payload = array(
            "username" => $this->appid,
            "timestamp" => time(),
            "serial_number" => $param['orderid'],
            "target_currency" => $config['currency'],
            "price" =>strval($param['amount']),
            "callback_url" => $config['notify_url'],
            "return_url" => $config['redirect_url'],
            "create_datetime" =>date('Y-m-d',strtotime($param['paytime']))
        );
        $signature = $this->makeSignature($payload);
        $payload["signature"] = $signature;
        $payload = array_filter($payload);
        $response = $this->httpRequest($url, "POST", json_encode($payload));
        save_log('gdpaid','提交参数:'.json_encode($payload).',接口返回信息：');
        $url ='';
        if(!empty($response)){
            $ret= create_folders('./public/order');
            file_put_contents('./public/order/'.$param['orderid'].'.html',$response);
            $domain = config('paydomain');
            $url = $domain.'/public/order/'.$param['orderid'].'.html';
        }
        return $url;
    }


    public function deposit($serial_number, $price, $target_currency, $callback_url, $return_url, $create_datetime)
    {
        $url = $this->domain . "/deposit";
        $payload = array(
            "username" => $this->appid,
            "timestamp" => time(),
            "serial_number" => $serial_number,
            "target_currency" => $target_currency,
            "price" => $price,
            "callback_url" => $callback_url,
            "return_url" => $return_url,
            "create_datetime" => $create_datetime
        );
        $signature = $this->makeSignature($payload);
        $payload["signature"] = $signature;
        $payload = array_filter($payload);
        $response = $this->httpRequest($url, "POST", json_encode($payload));
        return $response;
    }

    public function get_deposit_ticket($serial_number)
    {
        $url = $this->domain . "/merchant-deposit-ticket/" . $serial_number;
        $payload = array(
            "username" => $this->appid,
            "timestamp" => time()
        );
        $signature = $this->makeSignature($payload);
        $payload["signature"] = $signature;
        $url = $url . "?" . http_build_query($payload);
        $response = $this->httpRequest($url, "GET");
        return $response;
    }

    public function withdraw($serial_number, $ticket_date, $amount, $bank_name, $city,
                             $country, $province, $state, $address, $ifs_code, $account_name, $account_number, $phone_number,
                             $email, $pin_code, $remark, $callback_url)
    {
        $url = $this->domain . "/withdraw";
        $payload = array(
            "username" => $this->appid,
            "timestamp" => time(),
            "serial_number" => $serial_number,
            "ticket_date" => $ticket_date,
            "amount" => $amount,
            "bank_name" => $bank_name,
            "city" => $city,
            "country" => $country,
            "province" => $province,
            "state" => $state,
            "address" => $address,
            "ifs_code" => $ifs_code,
            "account_name" => $account_name,
            "account_number" => $account_number,
            "phone_number" => $phone_number,
            "email" => $email,
            "pin_code" => $pin_code,
            "remark" => $remark,
            "callback_url" => $callback_url
        );
        $signature = $this->makeSignature($payload);
        $payload["signature"] = $signature;
        $payload = array_filter($payload);
        $response = $this->httpRequest($url, "POST", json_encode($payload));
        return $response;
    }

    public function withdraw_by_upi($serial_number, $phone_number, $name, $amount, $upi_id, $email, $callback_url, $remark)
    {
        $url = $this->domain . "/withdraw/upi";
        $payload = array(
            "username" => $this->appid,
            "timestamp" => time(),
            "serial_number" => $serial_number,
            "amount" => $amount,
            "name" => $name,
            "phone_number" => $phone_number,
            "email" => $email,
            "upi_id" => $upi_id,
            "remark" => $remark,
            "callback_url" => $callback_url
        );
        $signature = $this->makeSignature($payload);
        $payload["signature"] = $signature;
        $payload = array_filter($payload);
        $response = $this->httpRequest($url, "POST", json_encode($payload));
        return $response;
    }

    public function get_withdraw_ticket($serial_number)
    {
        $url = $this->domain . "/merchant-withdraw-ticket/" . $serial_number;
        $payload = array(
            "username" => $this->appid,
            "timestamp" => time()
        );
        $signature = $this->makeSignature($payload);
        $payload["signature"] = $signature;
        $url = $url . "?" . http_build_query($payload);
        $response = $this->httpRequest($url, "GET");
        return $response;
    }

    public function get_balance()
    {
        $url = $this->domain . "/balance";
        $payload = array(
            "username" => $this->appid,
            "timestamp" => time()
        );
        $signature = $this->makeSignature($payload);
        $payload["signature"] = $signature;
        $url = $url . "?" . http_build_query($payload);
        $response = $this->httpRequest($url, "GET");
        return $response;
    }
}

//$sdk = new GDSdk("merchant", "secret_key");
//// 代收
//print($sdk->deposit("ABC0101", "100.00", "INR", "https://callback.com/callback", null, null));
//// 查詢代收訂單
//print($sdk->get_deposit_ticket("ABC0101"));
//// 代付
//print($sdk->withdraw("ABC0101", "2021-08-13", "100", "bank ", "city", "country", "province",
//"state", "address", "ifs_code", "your_account_name", "12345678", "7878787878", "email@gmail.com",
//"pin_code", "remark", "https://callback.com/callback"));
//// upi代付
//print($sdk->withdraw_by_upi("ABC0101", "100", "name", "upi@icici", "87456789", "mymail@gmail.com", "remark","https://callback.com/callback"));
//// 查詢代付訂單
//print($sdk->get_withdraw_ticket("ABC0101"));
//// 查詢餘額
//print($sdk->get_balance());
