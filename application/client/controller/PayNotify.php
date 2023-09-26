<?php

namespace app\client\controller;
use think\Exception;
use think\Controller;

class PayNotify extends Controller
{

    public function _empty()
    {

        try {
            $action = strtolower($this->request->action());
            if (strpos($action,'_')) {
    			$channel_code  = strtolower(explode('_', $action)[0]);
    			$action        = explode('_', $action)[1];
    			$class = '\\'.$channel_code.'\PaySdk';
    			$pay   = new $class();

                $params = $this->request->param() ?: json_decode(file_get_contents('php://input'),1);
                switch ($action) {
                    case 'notify':
                        save_log($channel_code, '代收通知:' . json_encode($params));
                        $where = 'type=0';
                        break;
                    case 'outnotify':
                        save_log($channel_code, '代付通知:' . json_encode($params));
                        $where = 'type=1';
                        break;
                    default:
                        # code...
                        break;
                }
                
                if (empty($params)) {
                    exit('fail:Empty Request');
                }
                $header = $this->request->header();
                $where   .= " and (ChannelCode='".$channel_code."' or ChannelCode='".ucwords($channel_code)."' or ChannelCode='".strtoupper($channel_code)."')";

                $channel_all = (new \app\model\MasterDB())->getTableRow('T_GamePayChannel', $where, '*');
                if (empty($channel_all)) {
                    exit('fail:Channel Not Exist');
                }
                $channel = json_decode($channel_all['MerchantDetail'],true);
                $channel['ChannelId'] = $channel_all['ChannelId'];
    			$pay->$action($params,$header,$channel,$channel_code);
            } else {
            	exit('fail');
            }
        } catch (Exception $ex) {
            save_log(strtolower(explode('_', strtolower($this->request->action()))[0]), 'Exception:' . $ex->getMessage() . $ex->getLine() . $ex->getTraceAsString());
            exit('fail');
        }
    }
}