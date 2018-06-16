<?php

/**
 * Created by PhpStorm.
 * User: Think
 * Date: 2018/6/14
 * Time: 17:58
 */

namespace App\Service;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\DomCrawler\Crawler;

class Helper
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
    public function post($body, $apiStr, $postType, $referer = null, $cookie_jar = null)
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

    public function get($apiStr, $cookie_jar = null, $referer = null)
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
    public function domCrawler($dom, $filterType, $filterRule)
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
}
