<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 2018/10/31
 * Time: 8:08 PM
 */

namespace App\Http\Controllers\Api;


use App\Http\Service\WechatService\Facades\WechatService;

class BaseAPIController extends Controller
{
    public function getAccessToken()
    {
        return WechatService::accessToken();
    }
}