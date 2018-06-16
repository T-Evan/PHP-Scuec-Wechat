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
        $method = "GET";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . config('app.api_weather'));
        $querys = "area=武汉&need3HourForcast=0&needAlarm=0&needHourData=0&needIndex=1&needMoreDay=0";
        $url = $host . $path . "?" . $querys;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $res = curl_exec($curl);
        $weather_array = json_decode($res, true);
        $now_weather_array = $weather_array['showapi_res_body']['now'];
        $info_weather_array = $weather_array['showapi_res_body']['f1']['index'];

        $temperature = $now_weather_array['temperature'] ."℃"; // 实时温度
        $humidity = $now_weather_array['sd']; // 实时湿度
        $info = $weather_array['showapi_res_body']['now']['weather']; // 实时天气
        $wind = $weather_array['showapi_res_body']['now']['wind_direction'].$weather_array['showapi_res_body']['now']['wind_power'];   // 实时风向
        $pm25 = $info_weather_array['aqi']['title'].", ".$info_weather_array['aqi']['desc'];  // 空气质量
        $ganmao = $info_weather_array['cold']['title']."，".$info_weather_array['cold']['desc'];   // 感冒指数
        $ziwaixian = $info_weather_array['uv']['title']."，".$info_weather_array['uv']['desc'];  // 紫外线指数
        $day_weather_pic = $now_weather_array['weather_pic'];
        $content = sprintf(
            "实时天气: %s，%s，%s，湿度: %s\n空气质量: %s\n感冒指数: %s\n紫外线指数: %s\n",
            $info,
            $temperature,
            $wind,
            $humidity,
            $pm25,
            $ganmao,
            $ziwaixian
        );
        $items = [
            new NewsItem(
                [
                    'title'       => '☞点击查看 未来七天天气预报',
                    'description' => $content,
                    'url'       => "http://mobile.weather.com.cn/city/101200101.html?data=7d",
                    'image' => $day_weather_pic
                ]
            )
        ];
        return $items;
    }
}
