<?php

namespace app\model;
class GiftCardReceive extends BaseModel
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
    public function T_GiftCardReceive(){
        $this->table="T_GiftCardReceive";
        return $this;
    }
}