<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/17
 * Time: 4:56
 */

namespace App\Http\Service;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\DomCrawler\Crawler;

class HelperService
{
    const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.94 Safari/537.36';

    /**
     * Notes:
     * @param $body
     * @param $apiStr
     * @param $postType
     * @param string $referer
     * @param null $cookie_jar
     * @return array|string
     */
    public static function post($body, $apiStr, $postType, $referer = null, $cookie_jar = null)
    {
        $client = new Client();

        if (empty($cookie_jar)) {
            $cookie_jar = new CookieJar();
        }
        $request_array = [
//                'debug' => true,
//                'proxy' => ['http' => 'http://localhost:8888'],
            'cookies' => $cookie_jar,
            $postType => $body,
            'headers' => [
                'User-Agent' => self::DEFAULT_USER_AGENT,
                'Referer' => $referer
            ]
        ];

        try {
//            $request = new Request('POST',$apiStr,$request_array);
//            $response = $client->send($request, ['timeout' => 2]);
            $res = $client->request('POST', $apiStr, $request_array);
        } catch (GuzzleException $e) {
            return $e->getMessage();
        }

        return array(
            'cookie' => $cookie_jar,
            'res' => $res
        );
    }

    public static function get($apiStr, $cookie_jar = null, $referer = null)
    {
        $client = new Client();
        if (empty($cookie_jar)) {
            $cookie_jar = new CookieJar();
        }
        $request_array = [
            'cookies' => $cookie_jar,
            //                'debug' => true,
            'headers' => [
                'User-Agent' => self::DEFAULT_USER_AGENT,
                'Referer' => $referer
            ],
        ];

        try {
            $res = $client->request('GET', $apiStr, $request_array);
        } catch (GuzzleException $e) {
            return $e->getMessage();
        }

        return array(
            'cookie' => $cookie_jar,
            'res' => $res
        );
    }

    /**
     * Notes:解析html函数，默认获取标签内文字，不支持获取某一标签中的属性
     * 解释失败默认返回false
     * @param $dom :Html文本
     * @param $filterType :解析类型,filter或filterXPath,参考domCrawler文档
     * @param $filterRule :解析规则
     * @return bool|string
     */
    public static function domCrawler($dom, $filterType, $filterRule)
    {
        $crawler = new Crawler();
        $crawler->addHtmlContent($dom);
        switch ($filterType) {
            case 'filter':
                try {
                    $res = $crawler->filter($filterRule)->text();
                } catch (\InvalidArgumentException $e) {
                    // Handle the current node list is empty..
                    return false;
                }
                return $res;
            case 'filterXPath':
                try {
                    $res = $crawler->filterXPath($filterRule)->text();
                } catch (\InvalidArgumentException $e) {
                    // Handle the current node list is empty..
                    return false;
                }
                return $res;
        }
    }

    /**
     * Notes:http://code.iamcal.com/php/emoji/
     * emoji代码参考自:
     * https://blog.csdn.net/lyq8479/article/details/9393097
     * 代码实现参考自:
     * https://blog.csdn.net/u012767761/article/details/71272780
     * 关于unicode编码的转化，显示emoji表情
     * @param $str
     * @return mixed
     */
    public static function getEmoji($str)
    {
        $str = '{"result_str":"'.$str.'"}';     //组合成json格式
        $strarray = json_decode($str, true);        //json转换为数组，同时将unicode编码转化成显示实体
        return $strarray['result_str'];
    }

    public static function getContent($str, $keyword)    // 匹配字符串中关键词后面的内容
    {
        $pregStr = "/(?<=".$keyword.").*/u";    // 正则表达式语法，向后查找
        preg_match($pregStr, $str, $matches);   // 使用向后查找可以匹配例如“图书图书”的情况
        $content = trim($matches[0]);   // 去除前后空格
        // http://www.php.net/manual/zh/function.strpos.php
        if (strpos($content, '+') !== false) {  // 如果获得的字符串前面有+号则去除
            $content = preg_replace("/\+/", '', $content, 1);   // 去除加号，且只去除一次，解决用户多输入+号的情况
            $content = trim($content);
        }
        return $content;
    }
}
