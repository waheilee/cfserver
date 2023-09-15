<?php

namespace app\model;
class UserDB extends BaseModel
{
    protected $table = '';

    /**
     * UserDB.
     * @param string $tableName 连接的数据表
     */
    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->UserDB);
    }
    public function TUserChannelPayOrder(){
        $this->table="T_UserChannelPayOrder";
        return $this;
    }
}