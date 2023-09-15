<?php

namespace app\active\controller;

use think\Controller;

class Base extends Controller
{


    /**
     * 输出错误JSON信息。
     * @param string $message
     * @param bool $options
     * @param int $code
     * @return false|string
     */
    protected function failJSON(string $message, bool $options = true, int $code = 1)
    {
        $jsonData = array('code' => $code, 'status' => false, 'msg' => $message);
        return json_encode($jsonData, $options);
    }

    /**
     * 输出成功JSON信息
     * @param $data
     * @param string $msg
     * @param int $options
     * @param int $code
     * @return false|string
     */
    protected function successJSON($data = NULL, string $msg = "success", int $options = 256, int $code = 0)
    {
        $jsonData = array('code' => $code, 'status' => true, 'data' => $data, 'msg' => $msg);
        return json_encode($jsonData, $options);
    }

}