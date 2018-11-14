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
use App\Models\StudentInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

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

    public function timetable(Request $request)
    {
        $account = $request->get('account');
        $response = function (int $status, array $data = []) {
            return [
                'status' => $status,
                'data' => $data
            ];
        };
        if (!$account) {
            return $response(405);
        }
        $userInfo = StudentInfo::where('account', $account)->first();
        if (!$userInfo) {
            return $response(405);
        }
        $timetableCache = Redis::connection('timetable')->get('timetable_'.$userInfo->openid);
        if (!$timetableCache) {
            return $response(404);
        }
        $timetableArr = json_decode($timetableCache, true);
        $timetableArr['current_week'] = SchoolDatetime::getSchoolWeek();
        return $response(200, $timetableArr);
    }
}