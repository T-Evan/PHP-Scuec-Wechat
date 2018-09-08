<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/17
 * Time: 4:56.
 */

namespace App\Http\Service;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\DomCrawler\Crawler;

class HelperService
{
    const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '.
    'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.94 Safari/537.36';

    /**
     * Notes:.
     *
     * @param $body
     * @param $apiStr
     * @param $postType
     * @param string $referer
     * @param null   $cookie_jar
     *
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
            'timeout' => 5,
            'headers' => [
                'User-Agent' => self::DEFAULT_USER_AGENT,
                'Referer' => $referer,
            ],
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
            'res' => $res,
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
            'timeout' => 3,
            'headers' => [
                'User-Agent' => self::DEFAULT_USER_AGENT,
                'Referer' => $referer,
            ],
        ];

        try {
            $res = $client->request('GET', $apiStr, $request_array);
        } catch (GuzzleException $e) {
            return $e->getMessage();
        }

        return array(
            'cookie' => $cookie_jar,
            'res' => $res,
        );
    }

    /**
     * Notes:解析html函数，默认获取标签内文字，不支持获取某一标签中的属性
     * 解释失败默认返回false.
     *
     * @param $dom :Html文本
     * @param $filterType :解析类型,filter或filterXPath,参考domCrawler文档
     * @param $filterRule :解析规则
     *
     * @return bool|string
     */
    public static function domCrawler($dom, $filterType, $filterRule, $attr = '')
    {
        $crawler = new Crawler();
        $crawler->addHtmlContent($dom);
        switch ($filterType) {
            case 'filter':
                try {
                    if ($attr) {
                        $res = $crawler->filter($filterRule)->attr($attr);
                    } else {
                        $res = $crawler->filter($filterRule)->text();
                    }
                } catch (\InvalidArgumentException $e) {
                    // Handle the current node list is empty..
                    return false;
                }

                return $res;
            case 'filterXPath':
                try {
                    if ($attr) {
                        $res = $crawler->filterXPath($filterRule)->attr($attr);
                    } else {
                        $res = $crawler->filterXPath($filterRule)->text();
                    }
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
     * 关于unicode编码的转化，显示emoji表情.
     *
     * @param $str
     *
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
        $pregStr = '/(?<='.$keyword.').*/u';    // 正则表达式语法，向后查找
        preg_match($pregStr, $str, $matches);   // 使用向后查找可以匹配例如“图书图书”的情况
        $content = trim($matches[0]);   // 去除前后空格
        // http://www.php.net/manual/zh/function.strpos.php
        if (false !== strpos($content, '+')) {  // 如果获得的字符串前面有+号则去除
            $content = preg_replace("/\+/", '', $content, 1);   // 去除加号，且只去除一次，解决用户多输入+号的情况
            $content = trim($content);
        }

        return $content;
    }

    public static function getBindingLink($type) // 获得绑定链接
    {
        $app = app('wechat');
        $message = $app->server->getMessage();
        $openid = $message['FromUserName'];
        if ('ssfw' == $type) {
            $bindingLink = '<a href="https://wechat.uliuli.fun/students/create/ssfw/'.
                $openid.'">〖绑定账号〗</a>';
        }
        if ('lib' == $type) {
            $bindingLink = '<a href="https://wechat.uliuli.fun/students/create/lib/'.
                $openid.'">〖绑定账号〗</a>';
        }
        if ('lab' == $type) {
            $bindingLink = '<a href="https://wechat.uliuli.fun/students/create/lab/'.
                $openid.'">〖绑定账号〗</a>';
        }
        if ('libName' == $type) {
            $bindingLink = '<a href="https://wechat.uliuli.fun/students/create/lib/'.
                $openid.'">〖绑定账号〗</a>';
        }

        return $bindingLink;
    }

    public static function todyInfo()
    {   //今天的详细信息，如今天的日期以及周数
        return SchoolDatetime::getDateInfo();
        $timeymd = date('Y年n月j日');
        $weektocn = array('Monday' => '星期一',
            'Tuesday' => '星期二',
            'Wednesday' => '星期三',
            'Thursday' => '星期四',
            'Friday' => '星期五',
            'Saturday' => '星期六',
            'Sunday' => '星期日',
        );
        $weekcn = $weektocn[date('l')];     //将获取到的英文星期信息转换为中文

        $weeknumcount = SchoolDatetime::getSchoolWeek();
        if ($weeknumcount > SchoolDatetime::getWeekCount()) { // 大于20周不显示周数
            $weeknumcountStr = '少年郎，我咖喱港，这学期干得不错，下学期8月27日、28日注册，8月29日正式上课。暑假快乐~/::P';
        } else {
            $weeknumcountStr = '本学期第'.$weeknumcount.'周';
        }

        $tody_info_str = $timeymd.' '.$weekcn."\n".$weeknumcountStr;

        return $tody_info_str;
    }

    /*
     * 老版资讯民大函数库
     */

    /**
     * Lets you determine whether an array index is set and whether it has a value.
     * If the element is empty it returns empty string (or whatever you specify as the default value.).
     *
     * Code from CodeIgniter
     *
     * @param   string
     * @param   array
     * @param   mixed
     *
     * @return mixed depends on what the array contains
     */
    public static function element($key, $array, $default = '')
    {
        return array_key_exists($key, $array) ? $array[$key] : $default;
    }

    /**
     * 生成随机字符串
     * Code from CodeIgniter.
     *
     * @param int $length 随机字符串的长度
     *
     * @return string $word
     */
    public static function randStr($length = 16)
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $word = '';
        for ($i = 0, $mt_rand_max = strlen($chars) - 1; $i < $length; ++$i) {
            $word .= $chars[mt_rand(0, $mt_rand_max)];
        }

        return $word;
    }

    /**
     * 发送 http(s) 请求（GET/POST/上传文件/JSON）.
     *
     * @param string $url     请求的url
     * @param mixed  $data    string/array
     * @param int    $timeout a value of timeout
     * @param string $cookie  a cookie string for request
     *
     * @return string 返回的结果
     */
    public static function httpRequest($url, $data = null, $timeout = null, $cookie = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);    // 返回原生输出
        curl_setopt($ch, CURLOPT_HEADER, 0);    // 不显示header头
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:')); // Disable Expect: header (some server does not support it)

        // https 请求
        if (false !== strpos($url, 'https://')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);    // 不检查SSL证书
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSLVERSION, 1);    // CURL_SSLVERSION_TLSv1
        }
        // POST 请求
        // To post a file, prepend a filename with @ and use the full path.
        // post JSON: string
        // 正常 POST 请求: urlencoded string
        if (!empty($data)) {
            // POST JSON
            if (self::is_JSON($data)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            }
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        if (!empty($timeout)) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        }
        if (!empty($cookie)) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    /**
     * 判断字符串是否是 JSON（PHP >= 5.3.0）.
     *
     * @param string $string 待判断的 JSON 串
     *
     * @return bool
     */
    public static function is_JSON($string)
    {
        return is_string($string) && is_object(json_decode($string))
        && (JSON_ERROR_NONE == json_last_error()) ? true : false;
    }

    /**
     * 从字符串（http头）中抽出Cookie.
     *
     * @param string $string 带http头的字符串
     *
     * @return mixed
     */
    public static function getCookieFromStr($string)
    {
        $isMatched = preg_match_all("/(Set-Cookie|Cookie):\s(.*?)\r\n/ism", $string, $arrCookies);  //一定要\r\n啊，否则抓到的Cookie会带有\r
        if ($isMatched) {
            $cookie = implode('; ', $arrCookies[2]);
        } else {
            $cookie = '';
        }

        return $cookie;
    }

    /**
     * remove all html entities from a htmlencoded string.
     *
     * @param string
     *
     * @return string
     */
    public static function removeHtmlEntities($rawStr)
    {
        $final = preg_replace('/&#?[a-z0-9]{2,8};/is', '', $rawStr);

        return $final;
    }

    /**
     * this function is from User Contributed Notes on http://php.net/base64_encode
     * return a special string(modify from base64) that can be part of the url.
     *
     * @param string
     *
     * @return string
     */
    public static function urlBase64Encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * this function is from User Contributed Notes on http://php.net/base64_encode
     * corresponding to urlBase64Encode().
     *
     * @param string
     *
     * @return string
     */
    public static function urlBase64Decode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
