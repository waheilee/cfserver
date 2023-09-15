<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2021/6/7
 * Time: 14:55
 */

namespace epay;

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
        $this->api_url = 'https://api.epay18.com';
        $this->merchant = '';
        $this->secretkey = '';
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
            'merchantCode' =>$this->merchant,
            'signType'=>'md5'
        ];

        $content=[
            'merchantCode'=>$this->merchant,
            'merchantTradeNo'=>trim($param['orderid']),
            'userId'=>strval($param['roleid']),
            'amount'=>sprintf('%.2f',$param['amount']),
            'notifyUrl'=>trim($config['notify_url']),
            'returnUrl'=> trim($config['redirect_url']),
            'terminalType'=>2,
            'channel'=>$config['code']
        ];
        $sign =$this->genSign($content,$config['secret']);
        $content['sign'] = $sign;
        $data['content'] = json_encode($content);

        $header = [
            'Content-Type: application/json;charset=utf-8',
        ];
        $result =$this->curl_post_content($this->api_url .'/pay/center/deposit/apply',json_encode($data), $header);
        save_log('epay','提交参数:'.json_encode($data).',接口返回信息：'.$result);
        $res = json_decode($result, true);
        if (!isset($res['status'])) {
            $res['message'] ='Http Request Invalid';
            //exit('Http Request Invalid');
        }
        $returl='';
        if(is_array($res)){
            if($res['status']=='SUCCESS')
            {
                $data =json_decode($res['data'],true);
                if(!empty($data))
                {
                    $content =json_decode($data['content'],true);
                    if(!empty($content['payUrl'])){
                        $returl = $content['payUrl'];
                    }
                }
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
                //$md5str = $md5str . $key . '=' . $val . '&';
                $md5str = $md5str .$val;
            }
        }
        $str =$md5str .$Md5key;
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