<?php

namespace App\Http\Controllers\Api;

use App\Models\WakeSignDetailInfo;
use Carbon\Carbon;
use App\Http\Controllers\Controller;

class WakeSignInfosController extends Controller
{
    public function show()
    {
        $user_signinfo = $this->user()->signInfo()->get()->first();
        if (empty($user_signinfo)) {
            return $this->response->array(['message' => '用户还没有开始打卡'])->setStatusCode(404);
        } else {
            $is_sign_today = WakeSignDetailInfo::where('openid', $this->user()->id)
                ->where('day_timestamp', Carbon::today())
                ->get()->first();
            $json_array = [
                'user_id' => $user_signinfo->user_id,
                'sign_day' => $user_signinfo->sign_day,
                'sign_score' => $user_signinfo->sign_score,
                'is_sign_today' => !empty($is_sign_today),
            ];

            return $this->response->array($json_array);
        }
    }

    public function getSignMessage()
    {
        /* 早起打卡*/
        $wakeSignDetail = new WakeSignDetailInfosController();
        $arrSignInfo = $wakeSignDetail->store();
        $test = new StudentWeixinInfosController();
        $nick = $test->getUserInfo('oULq3uBCIOqS4HqeJh4ldRnFXr5s');
        return $nick['nickname'];
    }
}
