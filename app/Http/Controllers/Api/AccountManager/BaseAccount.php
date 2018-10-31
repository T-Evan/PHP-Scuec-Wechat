<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 2018/10/30
 * Time: 4:52 PM
 */

namespace App\Http\Controllers\Api\AccountManager;


class BaseAccount
{
    protected $account;

    protected $password;

    protected $openid;

    /**
     * @return mixed
     */
    public function getOpenid()
    {
        return $this->openid;
    }

    /**
     * @param mixed $openid
     */
    public function setOpenid($openid)
    {
        $this->openid = $openid;
    }

    /**
     * @return mixed
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @param mixed $account
     */
    public function setAccount($account)
    {
        $this->account = $account;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param mixed $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }


}