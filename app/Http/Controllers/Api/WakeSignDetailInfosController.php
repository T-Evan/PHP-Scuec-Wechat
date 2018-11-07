<?php

namespace App\Http\Controllers\Api;

use App\Http\Service\OuterApiService;
use App\Models\WakeSignDetailInfo;
use App\Models\WakeSignInfo;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use EasyWeChat\Kernel\Messages\NewsItem;

class WakeSignDetailInfosController extends Controller
{
    const   TIME_WAKE_RANGE = '5:00~9:00';

    private $studentWeixinInfo;
    private $wakeSignInfo;
    private $wakeSignDetailInfo;

    private $now_time;
    private $today_time;
    private $begin_time;
    private $end_time;

    private $openid;

    public function __construct()
    {
        date_default_timezone_set('PRC');
        $this->now_time = Carbon::now()->timestamp;
        $this->today_time = Carbon::today()->timestamp;
        $this->begin_time = Carbon::today()->addHours(5)->timestamp; //五点开始打卡
        $this->end_time = Carbon::today()->addHours(9)->timestamp; //九点结束打卡

        $common = app('wechat_common');
        $this->openid = $common->openid;

        $studentWeixin = new StudentWeixinInfosController();
        $this->studentWeixinInfo = $studentWeixin->getUserInfo($this->openid);

        $this->wakeSignInfo = WakeSignInfo::where('openid', $this->openid)
            ->get()->first();

        $this->wakeSignDetailInfo = WakeSignDetailInfo::where('openid', $this->openid)
            ->where('day_timestamp', $this->today_time)
            ->get()->first();
    }

    public function store()
    {
//        dd(array(
//            'now_val' => $now_time,
//            'carbon_today' => Carbon::today(),
//            'today_val' => $today_time,
//            'begin_val' => $begin_time,
//            'end_val' => $end_time,
//        ));

        if ($this->now_time < $this->begin_time) {
            $message = '早起打卡时间为'
                        .self::TIME_WAKE_RANGE
                        ."\n今天的打卡还没有开始哦\n再睡会儿吧朋友~";

            return $message;
        }

        if ($this->now_time > $this->end_time && !$this->wakeSignDetailInfo) {
            $message = '早起打卡时间为'
                .self::TIME_WAKE_RANGE
                ."\n打卡已经结束了，明天继续加油~";

            return $message;
        }

        if (!empty($this->wakeSignDetailInfo)) {
            $replyMessage = $this->studentWeixinInfo['nickname']
                ."，今天已经打过卡啦~\n今天早起时间为"
                .date('H:i:s', $this->wakeSignDetailInfo->sign_timestamp)
                .'，民大第'
                .$this->wakeSignDetailInfo->sign_rank
                ."名\n"
                ."据说养成一个习惯需要21天,让早起成为一个习惯吧!\n明天请再接再厉哦~\n";

            return $replyMessage;
        }

        //第一次打卡
        if (!$this->wakeSignInfo) {
            $compareMessage = '初次见面，请多多关照ヽ(•ω•ゞ)';
            $signRes = WakeSignInfo::create([
                'openid' => $this->openid,
                'sign_day' => 1,
            ]);
        } else {
            $lastSign = WakeSignDetailInfo::where('openid', $this->openid)->orderBy('day_timestamp', 'desc')->get()->first();
            $lastSignTime = $lastSign->sign_timestamp;
            $signRes = $this->wakeSignInfo->update([
                'openid' => $this->openid,
                'sign_day' => $this->wakeSignInfo->sign_day + 1,
            ]);
        }

        $rank = WakeSignDetailInfo::where('day_timestamp', $this->today_time)->count();
        $signDetailRes = WakeSignDetailInfo::create([
            'openid' => $this->openid,
            'sign_timestamp' => $this->now_time,
            'day_timestamp' => $this->today_time,
            'sign_rank' => $rank + 1,
        ]);
        if ($signRes && $signDetailRes) {
            isset($lastSignTime) && $compareMessage = ($lastSignTime < $this->now_time)
                ? "\n噫~睡懒觉了哟\n".'上次早起时间是'.date('H:i:s', $lastSign->sign_timestamp)
                : "\n早起的鸟儿有虫吃(ง •̀_•́)ง\n";
            //reply success
            $replyMessage = '成功啦~这是坚持早起的第'.$signRes['sign_day']."天。\n"
                .'今天早起时间为'
                .date('H:i:s', $this->now_time)
                .'，民大第'
                .$signDetailRes->sign_rank."名。\n";

            $extraInfo = "\n- 早起打卡时间为".self::TIME_WAKE_RANGE;
            $clickInfo = "\n点击查看详情";
            $weatherInfo = "\n今日武汉天气".OuterApiService::weather('wakeSign');
            $signRankName = "\n今日打卡前三名为：\n";
            $rankUserArray = $this->getTodayRank($this->today_time);
            foreach ($rankUserArray as $user) {
                $signRankName .= $user->nickname.' '.$user->sign_timestamp."\n";
            }
            isset($compareMessage) && $replyMessage .= $compareMessage;
            $replyMessage .= $signRankName.$extraInfo.$weatherInfo.$clickInfo;
            $signday = '早起打卡'.'(第'.$signRes['sign_day'].'天)';
            $pngnumber = rand(1, 4); //随机选取四张封面图
            $pngurl = config('app.base_url').'/punch_card/img/shoutu'.$pngnumber.'.jpg';
            $news = [
                new NewsItem(
                    [
                        'title' => $signday,
                        'description' => $replyMessage,
                        'url' => config('app.base_url').'/punch_card/index.html?openid='.$this->openid.'?date='.time(),
                        'image' => $pngurl,
                    ]
                ),
            ];
            return $news;
        }
    }

    private function getTodayRank($timeStamp)
    {
        $studentWeixin = new StudentWeixinInfosController();
        $rankThree = WakeSignDetailInfo::where('day_timestamp', $timeStamp)->orderBy('sign_timestamp', 'asc')->get()->take(3);
        foreach ($rankThree as &$user) {
            $userInfo = $studentWeixin->getUserInfo($user->openid);
            $user['nickname'] = $userInfo['nickname'];
            $user['sign_timestamp'] = date('H:i:s', $user->sign_timestamp);
        }

        return $rankThree;
    }
}
