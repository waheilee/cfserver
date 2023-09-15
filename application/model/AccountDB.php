<?php

namespace app\model;
class AccountDB extends BaseModel
{
    protected $table = '';

    /**
     * UserDB.
     * @param string $tableName 连接的数据表
     */
    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->AccountDB);
    }
    public function T_DeviceToken(){
        $this->table="T_DeviceToken";
        return $this;
    }
}