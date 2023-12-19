<?php

namespace app\active\controller;

use app\model\AccountDB;
use app\model\GameOC;
use app\model\MasterDB;
use app\model\UserDB;

use socket\sendQuery;
use redis\Redis;
use think\Exception;

class Index extends Base
{

    public function getReward()
    {

        try {
            $phone = input('phone', '');
            $activeId = input('activeid', '');
            if (empty($phone) || empty($activeId)) {
                return $this->failJSON('The parameter is empty.');
            }
            $activeId = $this->decry('MDAwMDAwMD' . $activeId);
            $activeId = explode('_', $activeId)[1];
            $phone = trim($phone);
            if (substr($phone, 0, 1) == '+') {
                $phone = substr($phone, 1);
            }
            if (substr($phone, 0, 2) == '55' || substr($phone, 0, 2) == '63') {
                $phone = substr($phone, 2);
            }
            $rdskey = 'rewardphone:' . $phone;

            if (!$this->set_mutex($rdskey)) {
                return $this->failJSON('The request  are too frequent.');
            }

            $gameoc = new GameOC();
            $active_info = $gameoc->getTableRow('T_GiftCardActive', ['id' => $activeId], '*');

            if (!$active_info['Status']) {
                return $this->failJSON('The activity of collecting gift cards has not started yet.');
            }
            //活动时间判断
            $time = time();
            if (isset($active_info['StartTime']) && !empty($active_info['StartTime'])) {
                if ($time < strtotime($active_info['StartTime'])) {
                    return $this->failJSON('Por favor, recolha o vale-presente durante o horário do evento Hora de início da atividade:' . date('Y-m-d H:i:s', strtotime($active_info['StartTime'])));
                }
            }
            if (isset($active_info['EndTime']) && !empty($active_info['EndTime'])) {
                if ($time > strtotime($active_info['EndTime'])) {
                    return $this->failJSON('Por favor, recolha o vale-presente durante o horário do evento Horário de término do evento:' . date('Y-m-d H:i:s', strtotime($active_info['EndTime'])));
                }
            }

            $able_num = $active_info['TotalNum'] - $active_info['ReceiveNum'];

            if ($able_num <= 0) {
                if (config('tip_lang') == 'pt') {
                    return $this->failJSON('A atividade de coleta devales-presente foi encerrada.');
                } else {
                    return $this->failJSON('The activity of collecting gift cards has ended.');
                }

            }

            $db = new AccountDB();
            $account_info = $db->getTableRow('T_Accounts', 'AccountName=\'' . $phone . '\' or AccountName=\'55' . $phone . '\' or AccountName=\'63' . $phone . '\'', 'AccountID');
            if (empty($account_info['AccountID'])) {
                return $this->failJSON('The Phone or Email is not exists.');
            }

            $masterDB = new MasterDB();
            $config = $masterDB->getTableObject('T_GameConfig')
                ->where('CfgType', 302)
                ->value('CfgValue');//礼品卡每日领取一次限制配置

            if (!empty($config)) {
                $gameocdb = new GameOC();
                $extActivity = $gameocdb->getTableObject('T_GiftCardReceive')
                    ->where('RoleId',$account_info['AccountID'])
                    ->where('AddTime', '<', date('Y-m-d 23:59:59'))
                    ->select();
                if ($extActivity) {
                    return $this->failJSON('A contagem de sinistros de hoje está cheia.');
                }
            }

            //判断用户是否有当日充值，如果没有则不予领取
            $configDailyRecharge = $masterDB->getTableObject('T_GameConfig')
                ->where('CfgType', 303)
                ->value('CfgValue');//当日是否有充值

            if (!empty($configDailyRecharge)) {
                $userDB = new UserDB();
                $extRecharge = $userDB->getTableObject('T_UserChannelPayOrder')
                    ->where('AccountID',$account_info['AccountID'])
                    ->where('PayTime', '<', date('Y-m-d 23:59:59'))
                    ->select();
                if ($extRecharge) {
                    return $this->failJSON('Não foi recarregado hoje e não pode ser coletado.');
                }
            }

            //需要充值才能领取
            if ($active_info['NeedCharge'] == 1) {
                $userdb = new UserDB();
                $hasCharge = $userdb->getTableRow('View_Accountinfo', 'AccountName=\'' . $phone . '\' or AccountName=\'55' . $phone . '\' or AccountName=\'63' . $phone . '\'', 'TotalDeposit');
                if ($hasCharge['TotalDeposit'] <= 0) {
                    return $this->failJSON('Apenas os depositantes são elegíveis para recebe');
                }
            }
            //查看该用户今日是否有充值，没有充值则无法领取
            if ($active_info['TodayNeedCharge'] == 1) {
                $userDB = new UserDB();
                $extRecharge = $userDB->getTableObject('T_UserChannelPayOrder')
                    ->where('AccountID',$account_info['AccountID'])
                    ->where('PayTime', 'between time', [date('Y-m-d 00:00:00'),date('Y-m-d 23:59:59')])
                    ->select();
                if (empty($extRecharge)) {
                    return $this->failJSON('Não foi recarregado hoje e não pode ser coletado.');
                }
            }

            $strsql = 'SELECT count(1) as total  FROM [OM_GameOC].[dbo].[T_GiftCardReceive] where ActiveId=' . $activeId . ' and RoleId=' . $account_info['AccountID'];
            $receivelog = $gameoc->setTable('T_GiftCardReceive')->getTableQuery($strsql);

            if (isset($receivelog[0]['total'])) {
                if ($receivelog[0]['total'] > 0) {
                    return $this->failJSON('You have received the activity reward.');
                }
            } else {
                return $this->failJSON('Abnormal query record.');
            }

            $sendQuery = new  sendQuery();
            $res = $sendQuery->callback("CMD_MD_SYSTEM_MAILv2", [0, $account_info['AccountID'], 8, 9, $active_info['Amount'] * 1000, 0, $active_info['Wage'] * 10, 0, 1, $active_info['ActiveName'], $active_info['Descript'], '', '', '']);
            $retcode = unpack('Lint', $res)['int'];
            if ($retcode == 0) {
                $gamedb = new GameOC();
                //$receive_num =$active_info['ReceiveNum']+1;
                $gamedb->getTableObject('T_GiftCardActive')->where('id', $activeId)->setInc('ReceiveNum', 1);
                $save_data = [
                    'RoleId' => $account_info['AccountID'],
                    'ActiveId' => $activeId,
                    'Phone' => $phone,
                    'Amount' => $active_info['Amount'] * 1000,
                    'Wage' => $active_info['Wage'],
                    'AddTime' => date('Y-m-d H:i:s', time())
                ];
                $gamedb2 = new GameOC();
                $gamedb2->T_GiftCardReceive()->insert($save_data);
                save_log('giftcard', $account_info['AccountID'] . '，领取数据：' . json_encode($save_data) . ',状态：' . json_encode($res));
            } else {
                save_log('giftcard', $account_info['AccountID'] . '，领取状态失败，' . json_encode($res) . '发送失败');
            }
            $active_msg = config('active_msg');
            $msg = str_replace('{0}', $active_info['Amount'], $active_msg);
            return $this->successJSON('', $msg);
        } catch (Exception $ex) {
            save_log('giftcard', $ex->getMessage() . $ex->getTraceAsString());
            echo $ex->getMessage();
            return $this->failJSON('The system is not available now.');
        }
    }

    public function giftcardinfo()
    {
        $activeId = input('activeid', '');
        $activeId = $this->decry('MDAwMDAwMD' . $activeId);
        $activeId = explode('_', $activeId)[1];
        $gameoc = new GameOC();
        $active_info = $gameoc->getTableRow('T_GiftCardActive', ['id' => $activeId], 'ActiveName,TotalNum,ReceiveNum,Amount');
        unset($active_info['ROW_NUMBER']);
        $active_info['Remaining'] = $active_info['TotalNum'] - $active_info['ReceiveNum'];
        return $this->successJSON($active_info);
    }


    private function set_mutex($read_news_mutex_key, $timeout = 2)
    {
        $cur_time = time();
        $mutex_res = Redis::lock($read_news_mutex_key, $cur_time + $timeout);
        if ($mutex_res) {
            return true;
        }
        //就算意外退出，下次进来也会检查key，防止死锁
        $time = Redis::get($read_news_mutex_key);
        if ($cur_time > $time) {
            Redis::rm($read_news_mutex_key);
            return Redis::lock($read_news_mutex_key, $cur_time + $timeout);
        }
        return false;
    }

    /**
     * @param $uid
     * 释放锁
     */
    private function del_mutex($read_news_mutex_key)
    {
        Redis::rm($read_news_mutex_key);
    }

    //解密
    private function decry($str, $key = 'giftcard123')
    {
        return think_decrypt($str, $key);
        if (!$key) {
            return $str;
        }
        $data = base64_decode($str);
        $data = openssl_decrypt($data, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        return $data;
    }
}