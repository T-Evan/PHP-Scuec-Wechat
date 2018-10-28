<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 2018/10/27
 * Time: 11:15 AM
 */

namespace App\Http\Controllers\Api\AccountManager;


use GuzzleHttp\Cookie\CookieJar;

class AccountValidationResult
{
    protected $failed;
    protected $code;
    protected $cookie;
    protected $msg;

    public function __construct(bool $failed, int $code, CookieJar $cookie, string $msg = '')
    {
        $this->failed = $failed;
        $this->code = $code;
        $this->cookie = $cookie;
        $this->msg = $msg;
    }

    /**
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->failed;
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return CookieJar
     */
    public function getCookie(): CookieJar
    {
        return $this->cookie;
    }

    /**
     * @return string
     */
    public function getMsg(): string
    {
        return $this->msg;
    }


}