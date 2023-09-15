<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace firstpay;

use Utility\Utility;
use think\facade\Cache;
use app\model\MasterDB;
use app\model\UserDB;

class PaySdk
{


    private $api_url = '';
    private $notify_url = '';
    private $merchant = '';
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
        if (isset($config['appid']) && !empty($config['appid'])) {
            $this->merchant = $config['appid'];
        }
        if (isset($config['secret']) && !empty($config['secret'])) {
            $this->secret = $config['secret'];
        }
        if (isset($config['apiurl']) && !empty($config['apiurl'])) {
            $this->api_url = $config['apiurl'];
        }
        $merchant_no = $this->merchant;
        $orderid     = trim($param['orderid']);
        $amount      = sprintf('%.0f',$param['amount']);;
        $notify_url  = trim($config['notify_url']);
        $mobile      = rand(6,9).rand(100000000,999999999);
        $username    = chr(rand(65,90)).chr(rand(97,122)).chr(rand(97,122)).chr(rand(97,122)).chr(rand(97,122)).chr(rand(97,122));
        $email       = $mobile.'@gmail.com';
        $timestamp   = time();
        $data = [
            'app_order_id' =>$orderid,
            'amount'       =>(int)$amount,
            'phone'        =>$mobile,
            'user_name'    =>$username  
        ];

        $header = [
            'Content-Type:application/json;charset=UTF-8',
            'AppId:'.$this->merchant,
            'Signature:'.base64_encode(hash_hmac('sha256', json_encode($data), $this->secret))
        ];

        $result =$this->curl_post_content($this->api_url.'/v1/platform/collect_order',json_encode($data), $header);
        save_log('firstpay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['code'])) {
            $res['message'] ='Http Request Invalid';
        }
        $returl='';
        if(is_array($res)){
            if($res['code']==200)
                $returl= $res['data']['payment_link'];
        }

        return $returl;
    }

    //回调地址 /client/Pay_Notify/templatepay_notify
    public function notify($params,$header=[],$channel,$logname)
    {
        try {
            //参数
            $sign                  = $header['signature']??'';
            $data['json']          = json_encode($params);
            $data['orderid']       = $params['app_order_id']??'';
            $data['realmoney']     = $params['amount']??'';
            $data['transactionId'] = $params['order_id']??'';
            $data['code']          = $params['status'];
            $data['status']        = $params['status']??'' == '1' ? 1 : 0;
            //sign认证
            
            $checksign             = base64_encode(hash_hmac('sha256', json_encode($params), $channel['secret']));
            (new \paynotify\PayNotify(json_encode(['code'=>200,'msg'=>'success'])))->notify($data,$sign,$checksign,$channel,$logname);
        } catch (Exception $ex) {
            save_log($logname, 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            exit('fail');
        }
    }

    //回调地址 /client/Pay_Notify/templatepay_outnotify
    public function outnotify($params,$header=[],$channel,$logname){
        try {
            //参数
            $sign                  = $header['signature']??'';
            $data['json']          = json_encode($params);
            $data['orderid']       = $params['app_order_id']??'';
            $data['realmoney']     = $params['amount']??'';
            $data['transactionId'] = $params['order_id']??'';
            $data['code']          = $params['status'];
            switch ($data['code']) {
                case '1':
                    //成功
                    $data['status'] = 1;
                    break;
                case '2':
                    //失败
                    $data['status'] = 2;
                    break;
                case '0':
                    //处理中
                    $data['status'] = 3;
                    break;
                default:
                    $data['status'] = 0;
                    break;
            }
            $checksign             = base64_encode(hash_hmac('sha256', json_encode($params), $channel['secret']));

            (new \paynotify\PayNotify(json_encode(['code'=>200,'msg'=>'success'])))->outnotify($data,$sign,$checksign,$channel,$logname);
        }catch (Exception $ex){
            save_log($logname,'Exception:' . $ex->getMessage().$ex->getLine().$ex->getTraceAsString());
            exit('fail');
        }
    }


    private function createSign($data,$Md5key)
    {
        ksort($data);
        $md5str = '';
        foreach ($data as $key => $val) {
            if (trim($val) !== '') {
                $md5str = $md5str . $key . '=' . $val . '&';
            }
        }
        $str =$md5str . 'key=' . $Md5key;
        return strtolower(md5($str));
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
