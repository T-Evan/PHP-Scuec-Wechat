<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 2018/10/31
 * Time: 8:08 PM
 */

namespace App\Http\Controllers\Api;


use App\Http\Service\SchoolDatetime;
use App\Http\Service\WechatService\Facades\WechatService;

class BaseAPIController extends Controller
{
    public function getAccessToken()
    {
        return WechatService::accessToken();
    }

    public function testAuth()
    {
        return [
            'status' => 0
        ];
    }

    public function time()
    {
        return [
            'current_week' => SchoolDatetime::getSchoolWeek()
        ];
    }
}