<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace sepropay;

use Utility\Utility;
use think\facade\Cache;

class PaySdk
{


    private $api_url = '';
    private $notify_url = '';
    private $merchant = '';
    private $secretkey = '';




    public function __construct()
    {
        $this->api_url = 'https://pay.sepropay.com';
        $this->merchant = '977977802';
        $this->secretkey = '4fd2211409fa496cb4b8da10af4a6079';
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

        $data = [
            'version'=>'1.0',
            'mch_id' => $this->merchant,
            'notify_url' =>$config['notify_url'],
            'mch_order_no' => $param['orderid'],
            'pay_type'=>  $config['pay_type'],
            'trade_amount' => strval($param['amount']),
            'order_date' => $param['paytime'],
            'goods_name' => 'ID'.$param['AccountID']
        ];
        $data['sign'] =$this->genSign($data,$config['secret']);
        //$data['page_url'] ='';
        //$data['mch_return_msg']='';
        $data['sign_type']='MD5';
        //$data['bank_code']='';
        //$data['payer_phone']='';
        $header = [
            'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
        ];
        $result =$this->curl_post_content($this->api_url .'/pay/web', http_build_query($data), $header);
        save_log('sepropay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['respCode'])) {
            $res['message'] ='Http Request Invalid';
            //exit('Http Request Invalid');
        }
        $returl ='';
        if($res['respCode']=='SUCCESS') {
            if (!empty($res['payInfo'])) {
                $returl = $res['payInfo'];
            }
        }
        return $returl;
    }


    private function genSign($data,$Md5key)
    {
        ksort($data);
        $md5str = '';
        foreach ($data as $key => $val) {
            if (!empty(trim($val))) {
                $md5str = $md5str . $key . '=' . $val . '&';
            }
        }
        return strtolower(md5($md5str . 'key=' . $Md5key));
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