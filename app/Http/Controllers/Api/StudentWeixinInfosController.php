<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudentWeixinInfo;
use Illuminate\Support\Facades\Redis;

class StudentWeixinInfosController extends Controller
{
    public function getUserInfo($openid, $isRefresh = false)
    {
        $student = StudentWeixinInfo::where('openid', $openid)->get()->first();
        $student && $array = array('nickname' => $student->nickname, 'headimgurl' => $student->avatar);

        if (!$student || $isRefresh) {
            $access_token = $this->getToken();
            $jsoncode = json_decode(file_get_contents('https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN'), true);
            $errcode = $jsoncode['errcode'] ?? '20000';

            if ('42001' == $errcode || '41001' == $errcode) {
                $access_token = $this->getToken(true);
                $jsoncode = json_decode(file_get_contents('https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN'), true);
            }

            if (null == $jsoncode['nickname']) { //如果不能正常获取
                //$nickname='暂时无法获取名字>_<'.$key; # variable `key` is undefined
                $nickname = '暂时无法获取名字>_<';
            }

            $student = StudentWeixinInfo::where('openid', $openid);
            if (empty($student->get()->first())) {
                StudentWeixinInfo::create([
                    'openid'    => $openid,
                    'nickname'  => $jsoncode['nickname'],
                    'avatar'    => $jsoncode['headimgurl'],
                ]);
            } else {
                $student->update([
                    'nickname'  => $jsoncode['nickname'],
                    'avatar'    => $jsoncode['headimgurl'],
                ]);
            }
            $array = array('nickname' => $jsoncode['nickname'], 'headimgurl' => $jsoncode['headimgurl']);
        }

        return $array;
    }

    public function getToken($isRefresh = false)
    {
        $access_token = Redis::hGet('access_token', 'access_token');
        if (!$access_token || $isRefresh) {
            //TODO:上线后记得改过来
//            $appid = config('wechat.official_account.default.app_id');
            $appid = 'wxdfff0a26d620924e';
            $appsecret = '1d52a7d37f4b824d4c49110e872eb971';
//            $appsecret = config('wechat.official_account.default.secret');
            $jsoncode = json_decode(file_get_contents('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$appid.'&secret='.$appsecret));
            $access_token = $jsoncode->{'access_token'};
            Redis::hset('access_token', 'access_token', $access_token); //token缓存1小时
        }

        return $access_token;
    }
}
