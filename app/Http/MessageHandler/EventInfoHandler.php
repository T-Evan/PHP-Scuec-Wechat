<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/16
 * Time: 21:08
 */

namespace App\Http\MessageHandler;

use App\Models\Student;
use EasyWeChat\Kernel\Contracts\EventHandlerInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class EventInfoHandler implements EventHandlerInterface
{
    public function handle($message = null)
    {
        if ($message['MsgType'] == 'event') {
            switch ($message['Event']) {
                case 'subscribe':   //订阅
                    $content = "欢迎订阅资讯民大微信公众平台。\n您可以戳一下底部的菜单，常用功能都在里面哦，或者随便说点什么。/::P \n回复【帮助】查看我的全部技能。";
                    $user = Student::create(['openid' => $message['FromUserName'],]);
//                    $user->save();
                    return $content;
                    break;
                case 'unsubscribe': //取消订阅
                    $openid = $message['FromUserName'];
                    try {
                        Student::where('openid', $openid)->first()
                            ->delete();
                        Redis::del('ssfw_'.$openid); //清除办事大厅cookie缓存
                        Redis::connection('timetable')->del('timetable_'.$openid);
                        Redis::connection('exam')->del('exam_'.$openid);
                    } catch (\Exception $e) {
                        Log::error('openid：'.$message['FromUserName'].'  error：'.$e->getTraceAsString());
                    }
                    return true;
                    break;
                default:
                    $content = "亲，你按到火星上去了！/::L";
                    return $content;
                    break;
            }
        }
    }
}
