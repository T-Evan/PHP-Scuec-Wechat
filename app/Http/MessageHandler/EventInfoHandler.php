<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/16
 * Time: 21:08.
 */

namespace App\Http\MessageHandler;

use App\Http\Controllers\Api\AccountInfoController;
use App\Http\Controllers\Api\WakeSignDetailInfosController;
use App\Http\Service\HelperService;
use App\Models\Common;
use App\Models\StudentInfo;
use EasyWeChat\Kernel\Contracts\EventHandlerInterface;
use EasyWeChat\Kernel\Messages\News;
use EasyWeChat\Kernel\Messages\NewsItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Console\Helper\Helper;

class EventInfoHandler implements EventHandlerInterface
{
    public function handle($message = null)
    {
        if ('event' == $message['MsgType']) {
            switch ($message['Event']) {
                case 'subscribe':   //订阅
                    $content = "欢迎订阅资讯民大微信公众平台。\n您可以戳一下底部的菜单，常用功能都在里面哦，或者随便说点什么。/::P \n回复【帮助】查看我的全部技能。";
                    StudentInfo::create(['openid' => $message['FromUserName']]);

                    return $content;
                    break;
                case 'unsubscribe': //取消订阅
                    $openid = $message['FromUserName'];
                    try {
                        StudentInfo::where('openid', $openid)->first()
                            ->delete();
                        Redis::del('ssfw_'.$openid); //清除办事大厅cookie缓存
                        Redis::connection('timetable')->del('timetable_'.$openid);
                        Redis::connection('exam')->del('exam_'.$openid);
                    } catch (\Exception $e) {
                        Log::error('openid：'.$message['FromUserName'].'  error：'.$e->getTraceAsString());
                    }

                    return true;
                    break;
            }
        }
        if ('CLICK' == $message['Event']) {
            switch ($message['EventKey']) {
                case 'help':
                    $content = $this->helpStr();     //帮助信息文本
                    return $content;  //标准格式回复
                    break;
                case 'timetable':
                    $account = new AccountInfoController();
                    $content = $this->replyHandle($account, 'getTableMessage');

                    return $content;
                    break;
                case 'exam':
                    $account = new AccountInfoController();
                    $content = $this->replyHandle($account, 'getExamMessage');

                    return $content;
                    break;
                case 'signbutton': //临时解决延迟，如果你看到这行就删了吧
                case 'sign':
                    $account = new WakeSignDetailInfosController();
                    $content = $this->replyHandle($account, 'store');

                    return $content;
                case 'SchoolCalendar':
                    $items = [
                        new NewsItem(
                            [
                                'title' => '时刻表 | 民大生存必备，你要的时间都在这里！',
                                'description' => '时刻表每学期一更，维持民大最新的各地点记录～',
                                'url' => HelperService::wechatArticleUrl('https://mp.weixin.qq.com/s/3ytk6WYaWXT8HcLI1Y3SOQ'),
                                'image' => config('app.base_url').'/img/Calendar.jpg?v20181010',
                            ]
                        ),
                    ];

                    return new News($items);
                    break;
                default:
                    $content = '亲，你按到火星上去了！/::L';

                    return $content;
                    break;
            }
        }
    }

    //帮助信息文本。注意：下面的文字顶格换行微信里面显示也是这样
    private function helpStr()
    {
        $helpStr = '资讯民大功能菜单，回复括号里的关键词，get√
生活查询 :
'.HelperService::getEmoji("\ue04A").'【天气】 '.HelperService::getEmoji("\ue112").'【快递】
'.HelperService::getEmoji("\ue009").'【电话】 '.HelperService::getEmoji("\ue201").'【地图】
'.HelperService::getEmoji("\ue159").'【公交/地铁】
'.HelperService::getEmoji("\ue00C").'【电视直播】
'.HelperService::getEmoji("\ue01F").'【火车】
学习查询 :
'.HelperService::getEmoji("\ue157").'【课表】 '.HelperService::getEmoji("\ue44C").'【校历】
'.HelperService::getEmoji("\ue02B").'【考试】 '.HelperService::getEmoji("\ue14E").'【成绩】
'.HelperService::getEmoji("\ue345").'【翻译】 '.HelperService::getEmoji("\ue114").'【图书】
'.HelperService::getEmoji("\ue148").'【当前借阅】'.HelperService::getEmoji("\ue157").'【大物实验】
'.HelperService::getEmoji("\ue301").'【时刻表】

信息资讯 :
'.HelperService::getEmoji("\ue534").'【辅修】'.HelperService::getEmoji("\ue114").'【助学金】
'.HelperService::getEmoji("\ue302").'【教师证】'.HelperService::getEmoji("\ue157").'【医保】
'.HelperService::getEmoji("\ue114").'【号内搜】'.HelperService::getEmoji("\ue532").'【考证】

其它 :
'.HelperService::getEmoji("\ue428").'【微社区】
'.HelperService::getEmoji("\ue24e").'【关于】 '.HelperService::getEmoji("\ue327").'【帮推】
'.HelperService::getEmoji("\ue022").'【帮助】 '.HelperService::getEmoji("\ue103").'【反馈】
'.HelperService::getEmoji("\ue443").'【重新绑定】
'.HelperService::getEmoji("\ue019").'【历史消息】
更多功能努力研发ing';

        return $helpStr;
    }

    private function replyHandle($className, $fnName, $params = array())
    {
        try {
            $content = call_user_func_array(array($className, $fnName), $params);
            if (is_array($content)) {
                return new News($content);
            } else {
                return $content;
            }
        } catch (\Exception $exception) {
            Common::writeLog($exception->getMessage().$exception->getTraceAsString());
        }
    }
}
