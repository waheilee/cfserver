<?php

namespace app\index\controller;

use app\model\MasterDB;
use LOG\log;
use think\Controller;
use socket\sendQuery;
use app\model\BankDB;




class Index extends Controller
{

    public function BugSave(){
        $json = file_get_contents('php://input');
        if(!empty($json)){
            save_log('bugdata',$json);
        }
        return json(['code'=>0]);
    }


    public function index(){
        exit();
    }


    public function indextest() {
        $bankM = new BankDB();
        $orderid ='202305091517471180000';
        $order = $bankM->getTableRow('UserDrawBack',['OrderNo'=>$orderid],'AccountID,ChannelId,iMoney,status,DrawBackWay,FreezonMoney,CurWaged,NeedWaged');

        $order_coin = intval($order['iMoney']);
        $realmoney = intval($order_coin/1000);
        $sendQuery=new  sendQuery();
        $res = $sendQuery->callback("CMD_MD_USER_DRAWBACK_MONEY_NEW", [$order['AccountID'], 2, $orderid, $realmoney, $order_coin,$order['DrawBackWay'],$order['FreezonMoney'],$order['CurWaged'],$order['NeedWaged']]);
        $text = "OK";
        var_dump($res);
    }

    public function info() {
//        if (input('key')=='lsmir2')      phpinfo();

    }
    public function  log(){
        log::Init("testlog");
        log::INFO("12345");
    }

}
