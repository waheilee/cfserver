<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
if (!function_exists('IsNullOrEmpty')){
    /**
     * 判断是否为空或null
     * @param $value
     * @return bool
     */
    function IsNullOrEmpty($value){
        if (is_string($value)){
            $value=trim($value);
            return ($value==null || strlen($value)==0);
        }
        if (is_array($value)){
            return  (count($value)==0);
        }
        if (empty($value)) return empty($value);

    }
}



if (!function_exists('getClientIP')) {
    //获取客户端真实IP
    function getClientIP()
    {
        global $ip;
        if (getenv("HTTP_CLIENT_IP")) {
            $ip = getenv("HTTP_CLIENT_IP");
        } else if (getenv("HTTP_X_FORWARDED_FOR")) {
            $ip = getenv("HTTP_X_FORWARDED_FOR");
        } else if (getenv("REMOTE_ADDR")) {
            $ip = getenv("REMOTE_ADDR");
        } else {
            $ip = "Unknow";
        }

        return $ip;
    }
}


//http请求函数
function urlhttpRequest($url, $data = '', $headers = array(), $method = 'POST', $timeOut = 10, $agent = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeOut);           //请求超时时间
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeOut);    //链接超时时间
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        // https请求 不验证证书和hosts
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, $agent);
    if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($data != '') curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $file_contents = curl_exec($ch);
    curl_close($ch);
    return $file_contents;
}






function create_folders($dir)
{
    return is_dir($dir) or (create_folders(dirname($dir)) and mkdir($dir, 0777));
}

//记录log
if (!function_exists('save_log')) {
    function save_log($path, $content, $mode = 'day')
    {
        $path = strval($path);
        $path = str_replace('\\', '/', trim($path, '/'));

        $content = strval($content);

        if (!$path || !$content) {
            return false;
        }
        $mode     = in_array($mode, array('day', 'month', 'year')) ? $mode : 'day';
        $tempPath = config('log_dir') . '/' . $path . '/';

        if ($mode == 'day') {
            $tempPath .= date('Y') . '/' . date('m') . '/';
            $fileName = date('d') . '.log';
        } elseif ($mode == 'month') {
            $tempPath .= date('Y') . '/';
            $fileName = date('m') . '.log';
        } else {
            $fileName = date('Y') . 'log';
        }

        if (!file_exists($tempPath)) {
            if (!mkdir($tempPath, 0777, true)) {
                return false;
            }
        }

        $file    = $tempPath . $fileName;
        $content = date('Y-m-d H:i:s') . '#' . $content . "\r\n";
        $res     = @file_put_contents($file, $content, FILE_APPEND);
        if ($res) {
            return true;
        } else {
            return false;
        }
    }
}





function random_str($length)
{
    //生成一个包含  小写英文字母, 数字 的数组
    $arr = array_merge(range(0, 9), range('a', 'z'));
    $str = '';
    $arr_len = count($arr);
    for ($i = 0; $i < $length; $i++) {
        $rand = mt_rand(0, $arr_len - 1);
        $str .= $arr[$rand];
    }
    return $str;
}


/**
 * 系统加密方法
 * @param string $data 要加密的字符串
 * @param string $key  加密密钥
 * @param int $expire  过期时间 单位 秒
 * return string
 */
function think_encrypt($data, $key = '', $expire = 0) {
    $key  = md5(empty($key) ? 'crypt' : $key);
    $data = base64_encode($data);
    $x    = 0;
    $len  = strlen($data);
    $l    = strlen($key);
    $char = '';
    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) $x = 0;
        $char .= substr($key, $x, 1);
        $x++;
    }
    $str = sprintf('%010d', $expire ? $expire + time():0);
    for ($i = 0; $i < $len; $i++) {
        $str .= chr(ord(substr($data, $i, 1)) + (ord(substr($char, $i, 1)))%256);
    }
    return str_replace(array('+','/','='),array('-','_',''),base64_encode($str));
}
/**
 * 系统解密方法
 * @param  string $data 要解密的字符串 （必须是think_encrypt方法加密的字符串）
 * @param  string $key  加密密钥
 * return string
 */
function think_decrypt($data, $key = ''){
    $key    = md5(empty($key) ? 'crypt' : $key);
    $data   = str_replace(array('-','_'),array('+','/'),$data);
    $mod4   = strlen($data) % 4;
    if ($mod4) {
       $data .= substr('====', $mod4);
    }
    $data   = base64_decode($data);
    $expire = substr($data,0,10);
    $data   = substr($data,10);
    if($expire > 0 && $expire < time()) {
        return '';
    }
    $x      = 0;
    $len    = strlen($data);
    $l      = strlen($key);
    $char   = $str = '';
    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) $x = 0;
        $char .= substr($key, $x, 1);
        $x++;
    }
    for ($i = 0; $i < $len; $i++) {
        if (ord(substr($data, $i, 1))<ord(substr($char, $i, 1))) {
            $str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
        }else{
            $str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
        }
    }
    return base64_decode($str);
}