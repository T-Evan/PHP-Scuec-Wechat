<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/16
 * Time: 19:27
 */

namespace App\Http\MessageHandler;

use App\Http\Service\HelperService;
use App\Http\Service\OuterApiService;
use EasyWeChat\Kernel\Contracts\EventHandlerInterface;
use EasyWeChat\Kernel\Messages\News;
use EasyWeChat\Kernel\Messages\NewsItem;

class TextMessageHandler implements EventHandlerInterface
{
    public function handle($message = null)
    {
        $keyword = trim($message['Content']);
        $switchKey = $this->dealStr($keyword);

        switch ($switchKey) {
            case 'help':
                $content = $this->helpStr();     //帮助信息文本
                return $content;  //标准格式回复
                break;
            case 'weather':
                return OuterApiService::weather();
                break;
            case 'bus':
                $content = "<a href=\"http://m.amap.com/search/view/keywords=".$keyword."\">〖高德地图-".$keyword."〗</a>";//暂未找到免费公交查询接口,暂时跳转到高德地图查看
                return $content;
                break;
            case 'train':
                $trainNum = HelperService::getContent($keyword, "火车");   // 得到车次
                return OuterApiService::train($trainNum);
                break;
            case '课表':
                $items = [
                    new NewsItem(
                        [
                            'title'       => '收到你的图片了噢',
                            'description' => '我们会尽快处理',
                            'url'       => 'www.baidu.com',
                        ]
                    )
                ];
                return new News($items);

            case 'test2':
                return 'test2√';
            default:
                return $message['Content'].$message['FromUserName'];

        }
    }

    /**
     * 辅助函数
     * @param $keyword
     * @return string
     */

    private function dealStr($keyword) //字符串处理，用于确定用户的目的，正则匹配增加容错率
    {
        if (($keyword == '0') or ($keyword == '帮助')) { //此处有陷阱，如果字符串以合法的数字开头，就用该数字作为其值，否则其值为数字0。
            return 'help';
        } elseif ($keyword == '天气') {
            return 'weather';
        } elseif (preg_match("/^公交|^地铁/u", $keyword)) {
            return 'bus';
        } elseif (preg_match("/^火车/u", $keyword)) {
            return 'train';
        }
    }




    //帮助信息文本。注意：下面的文字顶格换行微信里面显示也是这样
    private function helpStr()
    {
        $helpStr = "资讯民大功能菜单，回复括号里的关键词，get√
生活查询 :
".HelperService::getEmoji("\ue04A")."【天气】 ".HelperService::getEmoji("\ue112")."【快递】
".HelperService::getEmoji("\ue009")."【电话】 ".HelperService::getEmoji("\ue201")."【地图】
".HelperService::getEmoji("\ue159")."【公交/地铁】
".HelperService::getEmoji("\ue00C")."【电视直播】
".HelperService::getEmoji("\ue01F")."【火车】
学习查询 :
".HelperService::getEmoji("\ue157")."【课表】 ".HelperService::getEmoji("\ue44C")."【校历】
".HelperService::getEmoji("\ue02B")."【考试】 ".HelperService::getEmoji("\ue14E")."【成绩】
".HelperService::getEmoji("\ue345")."【翻译】 ".HelperService::getEmoji("\ue114")."【图书】
".HelperService::getEmoji("\ue148")."【当前借阅】".HelperService::getEmoji("\ue157")."【大物实验】
".HelperService::getEmoji("\ue301")."【时刻表】

信息资讯 :
".HelperService::getEmoji("\ue534")."【辅修】".HelperService::getEmoji("\ue114"). "【助学金】
".HelperService::getEmoji("\ue302")."【教师证】".HelperService::getEmoji("\ue157")."【医保】
".HelperService::getEmoji("\ue114")."【号内搜】".HelperService::getEmoji("\ue532")."【考证】

其它 :
".HelperService::getEmoji("\ue428")."【微社区】
".HelperService::getEmoji("\ue24e")."【关于】 ".HelperService::getEmoji("\ue327")."【帮推】
".HelperService::getEmoji("\ue022")."【帮助】 ".HelperService::getEmoji("\ue103")."【反馈】
".HelperService::getEmoji("\ue443")."【重新绑定】
".HelperService::getEmoji("\ue019")."【历史消息】
更多功能努力研发ing";
        return $helpStr;
    }

}
