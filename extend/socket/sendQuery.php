<?php

namespace socket;
use think\log\driver\Socket;

/**
 * Class sendQuery
 * @package socket
 */
class sendQuery
{
    /**
     * @var Comm
     */
    private $comm;
    private $in_stream;

    /**
     * sendQuery constructor.
     */
    public function __construct() {
        $this->comm = new Comm();
        $this->in_stream= new PHPStream();
    }

    /**
     * @return Comm
     */
    public  function  getComm(){
        return $this->comm;
    }


    public function CMD_MD_USER_DRAWBACK_MONEY($socket, $iRoleID, $nStatus, $TransactionNo, $RealMoney, $iMonery,$payway) {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteULong($nStatus);
        $this->in_stream->WriteString($TransactionNo, 64);
        $this->in_stream->WriteINT64($RealMoney);
        $this->in_stream->WriteINT64($iMonery);
        $this->in_stream->WriteULong($payway);
        $in_head = $this->comm->MakeSendHead(52, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $socket->request($in_head, $in);
    }


    ///新的提现接口
    public function CMD_MD_USER_DRAWBACK_MONEY_NEW($socket, $iRoleID, $nStatus, $TransactionNo, $RealMoney, $iMonery,$payway,$FreezonMoney,$CurWaged,$NeedWaged) {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteULong($nStatus);
        $this->in_stream->WriteString($TransactionNo, 64);
        $this->in_stream->WriteINT64($RealMoney);
        $this->in_stream->WriteINT64($iMonery);
        $this->in_stream->WriteULong($payway);
        $this->in_stream->WriteINT64($FreezonMoney);
        $this->in_stream->WriteINT64($CurWaged);
        $this->in_stream->WriteINT64($NeedWaged);
        $in_head = $this->comm->MakeSendHead(52, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $socket->request($in_head, $in);
    }

    /**
     * 自定义回调函数
     * @param string $funcName   本类的函数名称
     * @param array $parameter   参数数组
     * @param string $SocketInstance 连接的socket对线 AS ,DC
     * @param null $changeFunc
     * @param null $changeDate
     * @return string
     */
    public function callback($funcName, $parameter, $SocketInstance='DC', $changeFunc = null, $changeDate = null) {
        $socket = $this->comm->getSocketInstance($SocketInstance);
        array_unshift($parameter, $socket);//往参数的头部插入 socket
        call_user_func_array([$this, $funcName], $parameter);
        $out_data = $socket->response();
        if (!empty($changeFunc)) {
            $change = new ChangeData();
            call_user_func([$change, $changeFunc], $changeDate);
        }
        return $out_data;
    }

    //生成手机验证码    CMD_WD_PHONE_SECCODE = 123

    /**
     * @param $socket
     * @param $szPhone
     */
    public function CMD_MD_PHONE_SECCODE($socket, $szPhone) {
        $this->in_stream->WriteString($szPhone, 16);
        $in_head = $this->comm->MakeSendHead(123, $this->in_stream->len, 0, REQ_OW, REQ_AI);
        $socket->request($in_head, $this->in_stream->data);
    }



    public function CMD_MD_PHONE_SECCODE_FiVE($socket, $szPhone) {
        $this->in_stream->WriteString($szPhone, 50);
        $in_head = $this->comm->MakeSendHead(123, $this->in_stream->len, 0, REQ_OW, REQ_AI);
        $socket->request($in_head, $this->in_stream->data);
    }


    ///未登录手机生成验证码
    public function CMD_WA_MAKE_PHONE_SECCODE($socket, $szPhone) {
        $this->in_stream->WriteString($szPhone, 50);
        $in_head = $this->comm->MakeSendHead(111, $this->in_stream->len, 0, REQ_OW, REQ_AI);
        $socket->request($in_head, $this->in_stream->data);
    }

    ///结束获取验证码
    public function CMD_WA_GET_PHONE_SECCODE($socket, $szPhone) {
        $this->in_stream->WriteString($szPhone, 50);
        $in_head = $this->comm->MakeSendHead(112, $this->in_stream->len, 0, REQ_OW, REQ_AI);
        $socket->request($in_head, $this->in_stream->data);
    }



    //mail 验证码
    public function CMD_MD_Mail_SECCODE($socket, $szPhone) {
        $this->in_stream->WriteString($szPhone, 64);
        $in_head = $this->comm->MakeSendHead(129, $this->in_stream->len, 0, REQ_OW, REQ_AI);
        $socket->request($in_head, $this->in_stream->data);
    }


    //重设密码
    public function CMD_MD_RESET_SECCODE($socket, $szPhone) {
        $this->in_stream->WriteString($szPhone, 16);
        $in_head = $this->comm->MakeSendHead(111, $this->in_stream->len, 0, REQ_OW, REQ_AI);
        $socket->request($in_head, $this->in_stream->data);
    }









    public function CMD_WD_TRANSFORM_FCM($socket,$iRoleID,$nType,$szFcmTitle,$szFcmMsg){
        $this->in_stream->WriteLong($iRoleID);
        $this->in_stream->WriteLong($nType);
        $this->in_stream->WriteString($szFcmTitle, 128);
        $this->in_stream->WriteString($szFcmMsg, 256);
        $in_head = $this->comm->MakeSendHead(131, $this->in_stream->len, 0, REQ_OW, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }



    public function ProcessAWGetSeccodeRes($out_data)
    {
        // echo "ProcessAWGetSeccodeRes: <br />";
        $out_data_array = unpack('LiCodeCount/', $out_data);
        for ($x=0; $x<$out_data_array['iCodeCount']; $x++)
        {
            $out_data_Count_array = unpack('x4/x'.($x*36).'/a32szLoginCode/LiCode', $out_data);
            $out_data_array["CodeInfoList"][$x] = $out_data_Count_array;
        }
        $out_array = $out_data_array;
        return $out_array;
    }





    /**
     * 第三方充值
     * @param Socket $socket
     * @param int $UserID
     * @param int $ChannelId
     * @param string $Torder char[64]
     * @param LONGLONG $RealMoney
     * @param LONGLONG $Money
     *  //1 首充礼包  2 充值返利 3商店充值
     */
    public function CMD_MD_USER_CHANNEL_RECHARGE($socket, $UserID, $ChannelId, $Torder,$CurrencyType,$RealMoney,$Money,$ChargeType,$AttenChargeAct){
        $this->in_stream->WriteULong($UserID);
        $this->in_stream->WriteULong($ChargeType);
        $this->in_stream->WriteULong($ChannelId);
        $this->in_stream->WriteString($Torder, 64);
        $this->in_stream->WriteString($CurrencyType, 32);
        $this->in_stream->WriteINT64($RealMoney);
        $this->in_stream->WriteINT64($Money);
        $this->in_stream->WriteULong($AttenChargeAct);
        $in_head = $this->comm->MakeSendHead(51, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $res=$socket->request($in_head, $in);
    }



    function CMD_MD_SYSTEM_MAILv2($socket, $iSendId, $iRoleID, $RecordType, $ExtraType, $iAmount, $PayOrder,$WageMul,$iDelaySecs, $mailType, $title, $mailtxt, $Note,$Country,$szOperator)
    {
        $this->in_stream->WriteULong($iSendId);
        $this->in_stream->WriteULong($iRoleID);
        $this->in_stream->WriteLong($RecordType);
        $this->in_stream->WriteLong($ExtraType);
        $this->in_stream->WriteUINT64($iAmount);
        $this->in_stream->WriteLong($PayOrder);
        $this->in_stream->WriteLong($WageMul);
        $this->in_stream->WriteLong($iDelaySecs);
        $this->in_stream->WriteLong($mailType); //nVerifyState  //0未审核 1 审核 2 删除
        $this->in_stream->WriteString($title, 64);
        $this->in_stream->WriteString($mailtxt, 512);
        $this->in_stream->WriteString($Note, 128);
        $this->in_stream->WriteString($Country, 32);
        $this->in_stream->WriteString($szOperator, 32);
        $in_head = $this->comm->MakeSendHead(50, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $socket->request($in_head, $this->in_stream->data);
    }

    public function SE_GIFT_CARD_GET($socket, $iRoleID, $RealMoney, $dm) {
        //$this->in_stream = new PHPStream();
        $this->in_stream->WriteLong($iRoleID);
        $this->in_stream->WriteLong($RealMoney);
        $this->in_stream->WriteLong($dm);
        $in_head = $this->comm->MakeSendHead(10008, $this->in_stream->len, 0, REQ_OM, REQ_DC);
        $in = $this->in_stream->data;
        $socket->request($in_head, $in);
    }

}



