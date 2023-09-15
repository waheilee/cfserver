<?php


namespace Utility;
/**
 * 工具类
 * @author xlj
 */
class Utility
{

    //发送短信接口中正
    /**
     * public static function sendSms($mobile, $content) {
     * $url = "http://182.254.136.167:8009/sys_port/gateway/index.asp?";
     * $data = "id=%s&pwd=%s&to=%s&Content=%s&time=";
     * $id = urlencode(iconv("utf-8", "gb2312", "itaiyang"));
     * $pwd = 'qwe!@#123';
     * $to = $mobile;
     * $content = urlencode(iconv("UTF-8", "GB2312", $content));
     * $rdata = sprintf($data, $id, $pwd, $to, $content);
     * $ch = curl_init();
     * curl_setopt($ch, CURLOPT_POST, 1);
     * curl_setopt($ch, CURLOPT_POSTFIELDS, $rdata);
     * curl_setopt($ch, CURLOPT_URL, $url);
     * curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
     * $result = curl_exec($ch);
     * curl_close($ch);
     * $code = substr($result, 0, 3);
     * if ($code === '000') {
     * return true;
     * } else {
     * return false;
     * }
     * }
     */

    /**
     * 获取IP
     */
    public static function getIP() {
        if (isset($_SERVER)) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $realip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $realip = $_SERVER['HTTP_CLIENT_IP'];
            } else {
                $realip = $_SERVER['REMOTE_ADDR'];
            }
        } else {
            //不允许就使用getenv获取
            if (getenv("HTTP_X_FORWARDED_FOR")) {
                $realip = getenv("HTTP_X_FORWARDED_FOR");
            } elseif (getenv("HTTP_CLIENT_IP")) {
                $realip = getenv("HTTP_CLIENT_IP");
            } else {
                $realip = getenv("REMOTE_ADDR");
            }
        }

        return $realip;
    }


    /**
     * 编码转换gb2312转utf8
     */
    public static function gb2312ToUtf8($str) {
        if (!empty($str))
            return iconv('gb2312', 'utf-8//IGNORE', $str);
        else
            return $str;
    }

    /**
     * utf8转gb2312
     * @param unknown_type $str
     * @return string
     */
    public static function utf8ToGb2312($str) {
        if (!empty($str))
            return iconv('utf-8', 'gb2312//IGNORE', $str);
        else
            return $str;
    }

    /**
     * 对参数进行Null和空判断
     * @param string $key 要判断的key
     * @param string $obj 要判断的类型对象,$_GET,$_POST
     */

    public static function isNullOrEmpty($key, $obj) {
        if (isset($obj[$key]) && !empty($obj[$key])) {
            return $obj[$key];
        }
        return FALSE;
    }

    /**
     * 判断是否数字
     * @param string $key 要判断的key
     * @param string $obj 要判断的类型对象,$_GET,$_POST
     */
    public static function isNumeric($key, $obj) {
        if (isset($obj[$key]) && is_numeric($obj[$key]) && $obj[$key] != '') {
            return intval($obj[$key]);
        }
        return FALSE;
    }

    /**
     * 计算页面数
     */
    public static function pageCount($iRecordsCount, $iPageSize) {
        return ceil($iRecordsCount / $iPageSize);
    }

    /**
     * 输出信息
     * @param $num
     */
    public static function output($msg) {
        echo($msg);
        exit();
    }


    /**
     * 随机数
     * @param $min 随机数最小值范围
     * @param $max 随机数最大值范围
     */
    public static function getRand($min, $max) {
        return rand($min, $max);
    }

    /**
     * 密码输入面板
     */
    public static function getRandomNum() {
        $arrNumber = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9);
        shuffle($arrNumber);
        //$_SESSION["arrRandNum"] = $arrNumber;
        return $arrNumber;
    }

    /**
     * 通过坐标得到相应位置的密码
     * @param unknown_type $arrPwd 密码组
     * @param unknown_type $strPwdCoordinate 坐标
     */
    public static function getPwd($arrPwd, $strPwdCoordinate) {
        $strPwd = "";
        $arrPass = str_split($strPwdCoordinate);
        foreach ($arrPass as $key) {
            $strPwd .= $arrPwd[$key];
        }
        return $strPwd;
    }

    /**
     * base64_decode 解密
     * @param $string 解密字符串
     */
    public static function mcryptDecrypt($arrConfig, $string) {
        return base64_decode($string);
        /*$key=$arrConfig['EncryptKey'];
        $crypttexttb=Utility::safe_base64Decode($string);//对特殊字符解析
        $decryptedtb = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256,md5($key),base64_decode($crypttexttb),MCRYPT_MODE_CBC,md5(md5($key))),"\0");
        return $decryptedtb;*/
    }

    /**
     * 检查密码强度
     * @param $password
     */
    public static function getPwdStrong($password) {
        $strStrong = "弱";

        $len = strlen(self::utf8ToGb2312($password));

        $matchCount = 0;
        if (preg_match("/[a-z]/", $password)) {
            $matchCount++;
        }
        if (preg_match("/[A-Z]/", $password)) {
            $matchCount++;
        }
        if (preg_match("/[0-9]/", $password)) {
            $matchCount++;
        }
        if (preg_match("/[^A-Za-z0-9]/", $password)) {
            $matchCount++;
        }

        if ($len >= 8 && $matchCount >= 2) {
            $strStrong = "中";
        }
        if ($len >= 10 && $matchCount >= 4) {
            $strStrong = "强";
        }

        return $strStrong;
    }

    /**
     * 解析特殊字符
     */
    public static function safe_base64Decode($string) {
        $data = str_replace(array('-', '_'), array('+', '/'), $string);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }

    /**
     * 接收json数据
     *
     * */
    public static function request($must_fields = null) {
        $params = (array)json_decode(file_get_contents('php://input'), true);
//        if (count($must_fields) > 0) {
//            foreach ($must_fields as $field) {
//                if (!isset($params[$field])) {
//                    Utility::response(-1, "param " . $field . " is not set ");
//                }
//            }
//        }
        return $params;
    }


    /**
     * 通过CURL发送HTTP请求
     * @param string $url 请求URL
     * @param array $postFields 请求参数
     * @param int $ContentType 请求类型 1=json,2=x-www-form-urlencoded
     * @param string $charset Default value="UTF-8"
     * @return bool|string
     */
    public static function curlPost($url, $postFields, $ContentType = 1, $charset = "UTF-8") {
        $header = [];
        $charset = "charset=$charset";
        switch ($ContentType) {
            case 1:
                $header = ["Content-Type: application/json; $charset"];
                break;
            case 2:
                $header = ["content-type: application/x-www-form-urlencoded;$charset"];
                $postFields = http_build_query($postFields);
                break;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); //若果报错 name lookup timed out 报错时添加这一行代码
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $ret = curl_exec($ch);
        if (false == $ret) {
            $result = curl_error($ch);
        } else {
            $rsp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 != $rsp) {
                $result = "请求状态 " . $rsp . " " . curl_error($ch);
            } else {
                $result = $ret;
            }
        }
        curl_close($ch);
        return $result;
    }

    /**
     * @param $code
     * @param $message
     * @param null $data
     * @param null $defaultData
     */
    public static function response($code, $message, $data = null, $defaultData = null) {
        $response = array('code' => $code, 'message' => $message);
        if ($data !== null) {
            if (is_object($data) || is_array($data)) {
                $response ['data'] = $data;
                //$response ['data'] = json_encode($data);
            } else {
                $response ['data'] = (string)$data;
            }
        } else {
            if ($defaultData !== null) {
                $response['data'] = json_encode($defaultData);
            }
        }

        // 返回结果
        echo json_encode($response);
        exit;
    }

    /**
     * Notes: 接口数据返回
     * @param $code
     * @param array $data
     * @param string $msg
     * @param int $count
     * @param array $other
     * @return mixed
     */
    public static function apiReturn($code, $data = [], $message = '') {
        return json([
            'code' => $code,
            'data' => $data,
            'message' => $message
        ]);
    }


    public static function get_web_page($url) {
        $options = array(
            CURLOPT_RETURNTRANSFER => true,     // return web page
            CURLOPT_HEADER => false,    // don't return headers
            CURLOPT_FOLLOWLOCATION => true,     // follow redirects
            CURLOPT_ENCODING => "",       // handle all encodings
            CURLOPT_AUTOREFERER => true,     // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 3,      // timeout on connect
            CURLOPT_TIMEOUT => 3,      // timeout on response
            CURLOPT_MAXREDIRS => 3,       // stop after 10 redirects
            CURLOPT_SSL_VERIFYPEER => false     // Disabled SSL Cert checks
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $content = curl_exec($ch);
        // $err     = curl_errno( $ch );
        // $errmsg  = curl_error( $ch );
        // $header  = curl_getinfo( $ch );
        curl_close($ch);

        // $header['errno']   = $err;
        // $header['errmsg']  = $errmsg;
        // $header['content'] = $content;
        return $content;
    }


    public function curl_get_page($url) {    //初始化
        $ch = curl_init();
        //设置选项，包括url
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        //执行并获取html内容
        $output = curl_exec($ch);
        //释放curl句柄
        curl_close($ch);
        return $output;
    }


    public function wxHttpsRequest($url, $data = null) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    public function spamcheck($field) {
        //filter_var() sanitizes the e-mail
        //address using FILTER_SANITIZE_EMAIL
        $field = filter_var($field, FILTER_SANITIZE_EMAIL);

        //filter_var() validates the e-mail
        //address using FILTER_VALIDATE_EMAIL
        if (filter_var($field, FILTER_VALIDATE_EMAIL)) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

}