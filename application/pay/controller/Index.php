<?php

namespace app\pay\controller;

use think\Controller;

class Index extends Controller
{
    public function onemxpay()
    {
        $amount = input('amount') ?: '0.00';
        $paycontent = input('paycontent') ?: '';

        $this->assign('amount',$amount);
        $this->assign('paycontent',$paycontent);
        return $this->fetch();
    }
}