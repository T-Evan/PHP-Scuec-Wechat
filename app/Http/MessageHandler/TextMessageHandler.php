<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/16
 * Time: 19:27
 */

namespace App\Http\MessageHandler;

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
                $host = "http://apifreelat.market.alicloudapi.com";
                $path = "/whapi/json/aliweather/briefforecast3days";
                $apiUrl=$host.$path;
                $weatherText = file_get_contents($apiUrl);
                if (strlen($weatherText) < 10) {
                    $contentstr = "出现错误，你可以稍后再试或把问题反馈给我们。";
                } else {
                    preg_match("/.*?(\{.+\}).*/", $weatherText, $matches);  // jsonp to json
                    $weatherJson = $matches[1]; // json数据
                    $weatherArray = json_decode($weatherJson, true);    // 转为数组

                    $RTtemperature = $weatherArray['realtime']['weather']['temperature']."℃";  // 实时温度
                    $RThumidity = $weatherArray['realtime']['weather']['humidity']."%"; // 实时湿度
                    $RTinfo = $weatherArray['realtime']['weather']['info']; // 实时天气
                    $RTwind = $weatherArray['realtime']['wind']['direct'].$weatherArray['realtime']['wind']['power'];   // 实时风向
                    $pm25 = $weatherArray['pm25']['quality']." ".$weatherArray['pm25']['aqi'];  // 空气质量
                    $ganmao = $weatherArray['life']['info']['ganmao']['0']."，".$weatherArray['life']['info']['ganmao']['1'];   // 感冒指数
                    $ziwaixian = $weatherArray['life']['info']['ziwaixian']['0']."，".$weatherArray['life']['info']['ziwaixian']['1'];  // 紫外线指数

                    $weather7d = '☞<a href="http://mobile.weather.com.cn/city/101200101.html?data=7d">未来七天天气预报</a>';

                    $contentstr = sprintf("实时天气: %s，%s，%s，湿度: %s，空气质量: %s\n感冒指数: %s\n紫外线指数: %s\n%s", $RTinfo, $RTtemperature, $RTwind, $RThumidity, $pm25, $ganmao, $ziwaixian, $weather7d);
                    $contentstr = $this->tody_info()."\n".$contentstr;
                }

                $resultStr = $this->ReplyText($object, $contentstr);
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
        }
    }


    /**
     * Notes:http://code.iamcal.com/php/emoji/
     * 关于unicode编码的转化，显示emoji表情
     * @param $str
     * @return mixed
     */
    private function getEmoji($str)
    {
        $str = '{"result_str":"'.$str.'"}';     //组合成json格式
        $strarray = json_decode($str, true);        //json转换为数组，同时将unicode编码转化成显示实体
        return $strarray['result_str'];
    }

    //帮助信息文本。注意：下面的文字顶格换行微信里面显示也是这样
    private function helpStr()
    {
        $helpStr = "资讯民大功能菜单，回复括号里的关键词，get√
生活查询 :
".$this->getEmoji("\ue04A")."【天气】 ".$this->getEmoji("\ue112")."【快递】
".$this->getEmoji("\ue009")."【电话】 ".$this->getEmoji("\ue201")."【地图】
".$this->getEmoji("\ue159")."【公交/地铁】
".$this->getEmoji("\ue00C")."【电视直播】
".$this->getEmoji("\ue01F")."【火车】
学习查询 :
".$this->getEmoji("\ue157")."【课表】 ".$this->getEmoji("\ue44C")."【校历】
".$this->getEmoji("\ue02B")."【考试】 ".$this->getEmoji("\ue14E")."【成绩】
".$this->getEmoji("\ue345")."【翻译】 ".$this->getEmoji("\ue114")."【图书】
".$this->getEmoji("\ue148")."【当前借阅】".$this->getEmoji("\ue157")."【大物实验】
".$this->getEmoji("\ue301")."【时刻表】

信息资讯 :
".$this->getEmoji("\ue534")."【辅修】".$this->getEmoji("\ue114"). "【助学金】
".$this->getEmoji("\ue302")."【教师证】".$this->getEmoji("\ue157")."【医保】
".$this->getEmoji("\ue114")."【号内搜】".$this->getEmoji("\ue532")."【考证】

其它 :
".$this->getEmoji("\ue428")."【微社区】
".$this->getEmoji("\ue24e")."【关于】 ".$this->getEmoji("\ue327")."【帮推】
".$this->getEmoji("\ue020")."【帮助】 ".$this->getEmoji("\ue103")."【反馈】
".$this->getEmoji("\ue443")."【重新绑定】
".$this->getEmoji("\ue019")."【历史消息】
更多功能努力研发ing";
        return $helpStr;
    }
}
