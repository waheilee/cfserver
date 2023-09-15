<?php
namespace sdk\SMS_SDK;
/* *
 * 类名：indiahmSDK
 * 功能：联动世纪 国际短信接口
 * 详细：构造联动世纪 短信接口请求，获取远程HTTP数据
 * 版本：1.0
 * 日期：2021-03-30
 */

use think\facade\Cache;
use Utility\Utility;

class indiahmSDK
{

    /// 国际
    /**
     *联动世纪 API账号
     */
    const API_ACCOUNT= '0330002'; //
    /**
     *联动世纪 API密码
     */
    const API_PASSWORD= '4j4rjt';
    /**
     * 联动世纪 短信接口URL utf-8
     */
    const API_SEND_URL='https://www.indiahm.com/sms/send';
    /**
     * 发送短信 联动世纪 国际接口
     *
     * @param string $mobile 手机号码
     * @param string $msg 短信内容
     * @param string $type OTP:验证码，  NOTIFY:通知短信，    MKT:营销短信，发送者显示为6位数字
     * @return mixed|string
     */
    public function SendSMS($mobile, $content)
    {
        //联动世纪 接口参数
        $postArr = [
            'mobile' => $mobile,
            'from'=>'XPROSP',//手机显示发送者，六位英文字符，需报
            'accessId' => self::API_ACCOUNT,
            'secret' => self::API_PASSWORD,
            'content' => $content,
            'type' => 'OTP',
        ];
        return  Utility::curlPost(self::API_SEND_URL, $postArr,2);

    }
}