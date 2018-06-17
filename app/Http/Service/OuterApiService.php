<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/17
 * Time: 4:55
 */

namespace App\Http\Service;

use EasyWeChat\Kernel\Messages\NewsItem;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

class OuterApiService
{
    public static function weather()
    {
        $host = "https://saweather.market.alicloudapi.com";
        $path = "/spot-to-weather";
        $querys = "area=武汉&need3HourForcast=0&needAlarm=0&needHourData=0&needIndex=1&needMoreDay=0";
        $url = $host . $path . "?" . $querys;
        $weatherText =  OuterApiService::apiCurlGet($url);
        if (strlen($weatherText) < 10) {
            $content = "出现错误，你可以稍后再试或把问题反馈给我们。";
        } else {
            $weather_array = json_decode($weatherText, true);
            $now_weather_array = $weather_array['showapi_res_body']['now'];
            $info_weather_array = $weather_array['showapi_res_body']['f1']['index'];

            $temperature = $now_weather_array['temperature'] . "℃"; // 实时温度
            $humidity = $now_weather_array['sd']; // 实时湿度
            $info = $weather_array['showapi_res_body']['now']['weather']; // 实时天气
            $wind = $weather_array['showapi_res_body']['now']['wind_direction'] . $weather_array['showapi_res_body']['now']['wind_power'];   // 实时风向
            $pm25 = $info_weather_array['aqi']['title'] . ", " . $info_weather_array['aqi']['desc'];  // 空气质量
            $ganmao = $info_weather_array['cold']['title'] . "，" . $info_weather_array['cold']['desc'];   // 感冒指数
            $ziwaixian = $info_weather_array['uv']['title'] . "，" . $info_weather_array['uv']['desc'];  // 紫外线指数
            $day_weather_pic = $now_weather_array['weather_pic'];
            $weather_content = sprintf(
                "实时天气: %s，%s，%s，湿度: %s\n空气质量: %s\n感冒指数: %s\n紫外线指数: %s",
                $info,
                $temperature,
                $wind,
                $humidity,
                $pm25,
                $ganmao,
                $ziwaixian
            );
            $content = [
                new NewsItem(
                    [
                        'title' => '☞点击查看 未来七天天气预报',
                        'description' => $weather_content,
                        'url' => "http://mobile.weather.com.cn/city/101200101.html?data=7d",
                        'image' => $day_weather_pic
                    ]
                )
            ];
        }
        return $content;
    }

    public static function train($train)
    {
        if (empty($train)) {
            $content = "输入括号里的关键字【火车+车次】即可查询列车时刻表，如【火车K472】。";
            return $content;
        }
        $host = "http://jisutrain.market.alicloudapi.com";
        $path = "/train/line";
        $querys = "trainno=$train";
        $url = $host . $path . "?" . $querys;
        $trainText =  OuterApiService::apiCurlGet($url);
        $train_array = json_decode($trainText, true);
        if ($train_array['status']=='203') {
            $content = "亲，貌似没有此趟列车，请核对后再查询。";
        } elseif ($train_array['status']=='0') {
            $train_list = $train_array['result']['list'];
            foreach ($train_list as $key => $value) {
                if ($key == '0') {
                    $start_station = $value['station']; //始发站
                    $departure_time = $value['departuretime']; //始发时间
                    $station_list = '始发站：'.$start_station.'，'.'出发时间：'.$departure_time."\n";
                    continue;
                }
                $station_list.='第'.$value['sequenceno'].'站：'.
                $value['station'].'，'.'到达时间：'.$value['arrivaltime'].'，'.
                '离开时间：'.$value['departuretime']."\n";
            }
            $content = HelperService::getEmoji("\ue01F").$train." 时刻表: \n".$station_list;
        } else {
            $content = "出现错误，你可以稍后再试或把问题反馈给我们。";
        }
        return $content;
    }
    public static function translate($word)
    {
        if (strlen($word)) {
            $host = "http://jisuzxfy.market.alicloudapi.com";
            $path = "/translate/translate";
            //根据字段是否为中文判断翻译类型
            if (preg_match("/[\x7f-\xff]/", $word)) {
                $querys = "from=zh-CN&text=$word&to=en&type=baidu";
            } else {
                $querys = "from=en&text=$word&to=zh-CN&type=baidu";
            }
            $url = $host . $path . "?" . $querys;
            $result=json_decode(OuterApiService::apiCurlGet($url), true);
            if ($result['status'] =='0') {
                $trans_word=$result['result']['result'];//取出翻译结果
                $content = "baidu翻译结果: ".strip_tags($trans_word);
            } else {
                $content = "出现错误，你可以稍后再试或把问题反馈给我们。";
            }
        } else {
            $content = "输入括号里的关键字【翻译+内容】即可智能翻译。如【翻译apple】、【翻译今天天气真好】。\n当需要翻译的语言为非中文时，会被翻译成中文。为中文时，会被翻译为英文。";
        }
        return $content;
    }
    private static function apiCurlGet($url)
    {
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . config('outapi.ali_APPCODE'));
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        return curl_exec($curl);
    }

    private static function apiCurlPost($url, $bodys)
    {
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . config('outapi.ali_APPCODE'));
        //根据API的要求，定义相对应的Content-Type
        array_push($headers, "Content-Type".":"."application/x-www-form-urlencoded; charset=UTF-8");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
        return curl_exec($curl);
    }
}
