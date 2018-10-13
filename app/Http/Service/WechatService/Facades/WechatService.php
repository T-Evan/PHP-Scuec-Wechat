<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 10/12/18
 * Time: 18:35
 */

namespace App\Http\Service\WechatService\Facades;


use App\Http\Service\WechatService\OAuth;
use App\Http\Service\WechatService\RetrieveUserInfo;
use Illuminate\Support\Facades\Facade;

class WechatService extends Facade
{
    protected static $userInfoRetriever     = null;
    protected static $oAuth                 = null;

    protected static function getFacadeAccessor()
    {
        return 'wechat';
    }

    public static function oauth()
    {
        if (!self::$oAuth){
            self::$oAuth = new OAuth();
        }
        return self::$oAuth;
    }

    public static function userinfo(string $code)
    {
        if (!self::$userInfoRetriever) {
            self::$userInfoRetriever = new RetrieveUserInfo($code);
        }
        return self::$userInfoRetriever;
    }
}