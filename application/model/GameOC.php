<?php

namespace app\model;
class GameOC extends BaseModel
{
    protected $table = '';

    /**
     * UserDB.
     * @param string $tableName 连接的数据表
     */
    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->GameOC);
    }

    public function PaynotifyLog(){
        $this->table="T_PayNotifyLog";
        return $this;
    }


    public function SmsCodeLog(){
        $this->table="T_SmsCodeLog";
        return $this;
    }

    public function T_GiftCardReceive(){
        $this->table="T_GiftCardReceive";
        return $this;
    }

    public function T_GiftCardActive(){
        $this->table="T_GiftCardActive";
        return $this;
    }

}