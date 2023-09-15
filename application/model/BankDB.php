<?php

namespace app\model;
class BankDB extends BaseModel
{
    protected $table = '';

    const DRAWBACK_STATUS_WAIT_PAY = 0; // 未付款
    const DRAWBACK_STATUS_AUDIT_PASS = 1; // 审核通过
    const DRAWBACK_STATUS_REFUSE_AND_RETURN_COIN = 2; // 拒绝并退还金币
    const DRAWBACK_STATUS_THIRD_PARTY_HANDLE_FAILED = 3; // 第三方失败
    const DRAWBACK_STATUS_THIRD_PARTY_HANDLING = 4; // 第三方处理中
    const DRAWBACK_STATUS_HANDLE_FAILED_AND_RETURN_COIN = 5; // 处理失败并退还金币
    const DRAWBACK_STATUS_ORDER_COMPLETED = 100; // 订单完成

    /**
     * UserDB.
     * @param string $tableName 连接的数据表
     */
    public function __construct($tableName = '') {
        if (!IsNullOrEmpty($tableName)) $this->table = $tableName;
        Parent::__construct($this->BankDB);
    }

    public function TUserDrawBack(){
        $this->table="UserDrawBack";
        return $this;
    }


}