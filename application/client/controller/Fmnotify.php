<?php

namespace app\client\controller;

use app\model\BankDB;
use app\model\GameOC;
use app\model\MasterDB;
use app\model\UserDB;
use log\log;
use redis\Redis;
use socket\sendQuery;
use think\Controller;
use think\Exception;
use think\facade\Cache;
use Utility\Utility;

class Fmnotify extends Controller
{
    private $withdraw_conf = [
        'api_url' => 'https://gateway.fmpay.org/withdraw/gateway',
        'secret' => '5ebfb4308a601e9717e8950dbb06b1f2',
        
    ];

    public function PayNotify()
    {
        $param =$_POST;
        save_log('fmpay','notify:' . json_encode($param));
        if(empty($param)){
            exit('Empty Request');
        }
        $order_id = $param['order_no'];
        $amount = $param['amount'];
        $transactionId = $param['trade_no'];
        $status = $param['code'];
        $sn = $param['sign'];

        if(empty($order_id) || empty($amount) || empty($sn) || empty($transactionId)){
            exit('Empty Request');
        }

        unset($param['sign']);
        $db = new UserDB();
        $order = $db->getTableRow('T_UserChannelPayOrder', ['OrderId' => $order_id], '*');
        if (empty($order)) {
            $gameoc = new GameOC();
            $data = [
                'OrderId' => $order_id,
                'Controller' => 'Notify',
                'Method' => __METHOD__,
                'Parameter' => json_encode($param),
                'Error' => '订单不存在',
                'AddTime' => date('Y-m-d H:i:s', time())
            ];
            $gameoc->PaynotifyLog()->insert($data);
            exit('ok');
        }

        $ChannelId=$order['ChannelID'];
        $masterA = new MasterDB();
        $channel = $masterA->getTableRow('T_GamePayChannel', ['channelID' => $ChannelId], '*');
        $detail = json_decode($channel['MerchantDetail'], true);
        $sign = $this->genSn($param, $detail['secret']);
        $text = 'ok';
        if ($sign == $sn) {
            if($status=="00") {
                $log_txt = '';
                if ($order['Status'] != 0) {
                    $gameoc = new GameOC();
                    $data = [
                        'OrderId' => $order_id,
                        'Controller' => 'Notify',
                        'Method' => __METHOD__,
                        'Parameter' => json_encode($param),
                        'Error' => '订单已经支付或其他状态',
                        'AddTime' => date('Y-m-d H:i:s', time())
                    ];
                    $gameoc->PaynotifyLog()->insert($data);
                    exit($text);
                }
                $masterB = new MasterDB();
                $res = $masterB->getTableRow('T_ShopItem', 'RealMoney=' . $amount, '*');

                if($res){
                    $Money = $res['BaseGoodsValue']; //实际金币 对应表 masterdb.t_shopitem BaseGoodsValue
                    $sendQuery = new  sendQuery();
                    $res = $sendQuery->callback('CMD_MD_USER_CHANNEL_RECHARGE', [$order['AccountID'], $ChannelId, $transactionId, $detail['currency'],$amount, $Money]);
                    $code = unpack("LCode", $res)['Code'];
                    if (intval($code) === 0) {
                        $data = [
                            'PayTime' => date('Y-m-d H:i:s', time()),
                            'Status' => 1,
                            'TransactionNo' => $transactionId
                        ];
                        $user = new UserDB();
                        $ret = $user->updateTable('T_UserChannelPayOrder', $data, ['OrderId' => $order_id]);
                        if ($ret)
                            $log_txt = '订单充值成功';
                        else
                            $log_txt = '充值成功，订单状态更新失败';
                    } else {//上分失败
                        $data = [
                            'PayTime' => date('Y-m-d H:i:s', time()),
                            'Status' => 2,
                            'TransactionNo' => $transactionId
                        ];
                        $user = new UserDB();
                        $ret = $user->updateTable('T_UserChannelPayOrder', $data, ['OrderId' => $order_id]);
                        if ($ret)
                            $log_txt = '金币未发放成功';
                        else
                            $log_txt = '金币未发放成功,订单状态更新失败';
                    }
                }
                else{
                    $data = [
                        'PayTime' => date('Y-m-d H:i:s', time()),
                        'Status' => 2,
                        'TransactionNo' => $transactionId
                    ];
                    $user = new UserDB();
                    $ret = $user->updateTable('T_UserChannelPayOrder', $data, ['OrderId' => $order_id]);
                    if ($ret)
                        $log_txt = '充值成功，金币未发放';
                    else
                        $log_txt = '金币未发放,订单状态更新失败';
                }

                $gameoc = new GameOC();
                $data = [
                    'OrderId' => $order_id,
                    'Controller' => 'Notify',
                    'Method' => __METHOD__,
                    'Parameter' => json_encode($param),
                    'Error' => $log_txt,
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $gameoc->PaynotifyLog()->insert($data);
            }
            else{
                $data = [
                    'PayTime' => date('Y-m-d H:i:s', time()),
                    'Status' => 3,
                    'TransactionNo' => $transactionId
                ];
                $user = new UserDB();
                $ret = $user->updateTable('T_UserChannelPayOrder', $data, ['OrderId' => $order_id]);

                $gameoc = new GameOC();
                $data = [
                    'OrderId' => $order_id,
                    'Controller' => 'Notify',
                    'Method' => __METHOD__,
                    'Parameter' => json_encode($param),
                    'Error' => '订单支付失败',
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $gameoc->PaynotifyLog()->insert($data);
            }

        } else {
            $gameoc = new GameOC();
            $data = [
                'OrderId' => $order_id,
                'Controller' => 'Notify',
                'Method' => __METHOD__,
                'Parameter' => json_encode($param),
                'Error' => '签名错误',
                'AddTime' => date('Y-m-d H:i:s', time())
            ];
            $gameoc->PaynotifyLog()->insert($data);
        }
        exit($text);
    }
    

    public function PayOutNotify()
    {
        $params = $_POST; //POST参数 POST parameters
        $gameoc = new GameOC();
        $log_data = [
            'OrderId' => $params['order_no'],
            'Controller' => 'Notify',
            'Method' => __METHOD__,
            'Parameter' => json_encode($params),
            'AddTime' => date('Y-m-d H:i:s', time())
        ];
        
        $BankDB = new \app\model\BankDB();
        // 开启事务
        $query = $BankDB->getTransDBConn();
        $query->startTrans();
        try {
            save_log('fmpay','payoutnotify:' . json_encode($params));
            if(empty($params)) {
               throw new \Exception('Empty Request'); 
            }
            $status = $params['code'];
            $orderid = $params['order_no'];
            $transactionId = $params['trade_no'];
            $realmoney = $params['amount'];
            $sign = $params['sign'];
            $partner_id = $params['partner_id'];
            
            if(empty($orderid) || empty($realmoney) || empty($sign) || empty($status)){
                throw new \Exception('Empty Request');
            }
            // 获取提现订单
            $bankM = new BankDB();
            $order = $bankM->getTableRow('userdrawback',['OrderNo'=>$orderid],'AccountID,ChannelId,iMoney,status');
            if (empty($order)) {
                throw new \Exception('订单不存在');
            }
            // 验证签名
            $masterdb = new  MasterDB();
            $row = $masterdb->getTableRow('T_GamePayChannel', ['channelID' => $order['ChannelId']], '*');
            $channel = json_decode($row['MerchantDetail'], true);
            if (isset($channel['appid']) && $partner_id != $channel['appid']) {
                throw new \Exception('商户编号错误 partner_id='.$partner_id.', appid='.$channel['appid']);
            }
            unset($params['sign']);
            $mysign = $this->genSn($params, $channel['secret']); //生成签名 Generate signature
             //验签 Verify signature
            if ($sign != $mysign) {
                throw new \Exception('签名错误');
            }
            $order_coin = intval($order['iMoney']);
            if (intval($order['status']) != $BankDB::DRAWBACK_STATUS_THIRD_PARTY_HANDLING) {
                throw new \Exception('订单状态不正确，非提交三方处理中状态');
            }
            $save_data = [
                'TransactionNo' => $transactionId,
                'UpdateTime' => date('Y-m-d H:i:s', time())
            ];
            $order_update_where = ['OrderNo' => $orderid, 'status'=>$BankDB::DRAWBACK_STATUS_THIRD_PARTY_HANDLING];
            
            $sendQuery = new sendQuery();
            if (strval($status) == '00') {
                // 成功通知
                $save_data['status'] = $BankDB::DRAWBACK_STATUS_ORDER_COMPLETED;
                $save_data['IsDrawback'] = $BankDB::DRAWBACK_STATUS_ORDER_COMPLETED;
                $ret = $BankDB->setTable('userdrawback')->UPData($save_data, $order_update_where);
                if (!$ret) {
                    throw new \Exception('订单更新失败');
                } 
                
                $realmoney = intval($order_coin / 1000);
                $result = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY", [$order['AccountID'], $BankDB::DRAWBACK_STATUS_AUDIT_PASS, $orderid, $realmoney, $order_coin]);
                $code = unpack("Cint", $result)['int'];
                if ($code != 0) {
                    throw new \Exception('三方成功通知, 游戏服务端处理返回失败 code:'. $code);
                }
                $log_data['Error'] = '三方处理成功, 游戏服务端返回成功 code:'.$code;
            } else {
                // 失败通知
                $save_data['status'] = $BankDB::DRAWBACK_STATUS_HANDLE_FAILED_AND_RETURN_COIN;
                $save_data['IsDrawback'] = $BankDB::DRAWBACK_STATUS_HANDLE_FAILED_AND_RETURN_COIN;
                $ret = $BankDB->setTable('userdrawback')->UPData($save_data, $order_update_where);
                if (!$ret) {
                    throw new \Exception('订单更新失败');
                } 
                $code = 1;
                $realmoney = intval($order_coin / 1000);
                $result = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY", [$order['AccountID'], $BankDB::DRAWBACK_STATUS_REFUSE_AND_RETURN_COIN, $orderid, $realmoney, $order_coin]);
                $code = unpack("Cint", $result)['int'];
                if ($code != 0) {
                    throw new \Exception('三方失败通知, 游戏服务端处理返回失败 code:'.$code);
                }
                $log_data['Error'] = '三方处理失败, 游戏服务端返回成功 code:'.$code;
            }           
            
            $gameoc->PaynotifyLog()->Insert($log_data);
            // 事务提交
            $query->commit();
            exit('ok');
            
        } catch (Exception $ex) {
            // 事务回滚
            $query->rollback();
            $msg = $ex->getMessage();
            save_log('fmpay','Exception:' . $msg.$ex->getLine().$ex->getTraceAsString());
            $log_data['Error'] = $msg;
            $gameoc->PaynotifyLog()->insert($log_data);
            exit($msg);
        }

    }


    private function genSn($data,$secret)
    {
        // 按照ASCII码升序排序
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $value = trim($value);
            if ('sign' != $key && '' != $value) {
                $str.= $key .'=' . $value . '&';
            }
        }
        $str .= $secret;
        return strtolower(md5($str));
    }


}