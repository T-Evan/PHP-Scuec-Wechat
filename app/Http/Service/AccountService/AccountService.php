<?php
/**
 * 此服务用于提供基础的用户账户管理功能
 * 包括:
 *      获取当前用户访问的ID
 *      获取当前用户对应的Model
 *      查询用户是否绑定过账户
 *      获取大物实验的账户及是否绑定
 *
 * User: yaphper
 * Date: 10/12/18
 * Time: 18:07
 */

namespace App\Http\Service\AccountService;



use App\Http\Service\WechatService\Facades\WechatService;
use App\Models\StudentInfo;

class AccountService
{
    private $openid;

    private $message;

    private $user = null;

    public function __construct()
    {
        $this->message = app('wechat')->server->getMessage();
        $this->openid = $this->message['FromUserName'] ?? null;
    }

    /**
     * @return null
     */
    public function getOpenid()
    {
        return $this->openid;
    }

    /**
     * @return StudentInfo|\Illuminate\Database\Eloquent\Model|null|object
     */
    public function getAccount()
    {
        if (!$this->openid) {
            return null;
        }
        if (!$this->user) {
            $this->user = StudentInfo::where('openid', $this->openid)->first();
        }
        return $this->user;
    }

    /**
     * 是否绑定教务系统
     *
     * @return bool
     */
    public function isBindSSFW(): bool
    {
        $account = $this->getAccount();
        if (!$account) {
            return false;
        }
        if ($account->ssfw_password == null || $account->ssfw_password == '') {
            return false;
        }
        return true;
    }

    /**
     * 是否绑定大物实验账户
     *
     * @return bool
     */
    public function isBindLib(): bool
    {
        $account = $this->getAccount();
        if (!$account) {
            return false;
        }
        if ($account->lib_password == null || $account->lib_password == '') {
            return false;
        }
        return true;
    }
}