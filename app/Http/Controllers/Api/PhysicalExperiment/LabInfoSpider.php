<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 2018/10/24
 * Time: 5:37 PM
 */

namespace App\Http\Controllers\Api\PhysicalExperiment;


use App\Http\Service\HelperService;
use App\Http\Service\SchoolDatetime;

class LabInfoSpider
{
    protected $arrTable;
    protected $id;
    protected $password;
    protected $cookie;

    /**
     * @param string $userId the user ID
     * @param string $userPasswd the user password
     */
    function __construct($userId, $userPasswd)
    {
        $this->id = $userId;
        $this->password = $userPasswd;
        $this->cookie = null;
    }

    /**
     * get cookie from the website
     * in order to keep the cookie in memory instead of the disk whose I/O is much slower, I set it
     * to null and the cookie will be read from the HTTP response header. By the way, it is more
     * easier to store the cookie into the database.
     * @return array
     */
    public function getCookie($needRefresh = false)
    {
        if ($this->cookie !== null) {
            if ($needRefresh == false) {
                return array(
                    'status' => 200,
                    'message' => __CLASS__ . ": get cookie successfully.",
                    'data' => $this->cookie
                );
            }
        }
        /* 获取成功登录后的Cookie */
        $ch = curl_init();
        $cookieJar = null;
        $curlPostFields = "mytype=student&myid={$this->id}&mypasswd={$this->password}&OK=%C8%B7%B6%A8";
        $curlOptions = array(
            CURLOPT_URL => 'http://labsystem.scuec.edu.cn/login.php',
            CURLOPT_USERAGENT => HelperService::DEFAULT_USER_AGENT,
            CURLOPT_REFERER => 'http://labsystem.scuec.edu.cn',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $curlPostFields,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true
        );
        curl_setopt_array($ch, $curlOptions);
        $htmlData = curl_exec($ch);
        if ($htmlData == false) {
            return array(
                'status' => 404,
                'message' => __CLASS__ . ": connection timed out",
                'data' => null
            );
        }
        # DEBUG test if strpos() really works here.
        if (strpos($htmlData, "main") === false) {
            return array(
                'status' => 403,
                'message' => __CLASS__ . ": auth failed (wrong username or password)",
                'data' => null
            );
        }
        /* save the cookie into an array */
        $httpHeaderSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpHeader = substr($htmlData, 0, $httpHeaderSize);    //抽出http响应头
        $this->cookie = HelperService::getCookieFromStr($httpHeader);
        return array(
            'status' => 200,
            'message' => __CLASS__ . ": get cookie successfully.",
            'data' => $this->cookie
        );
    }

    public function setRawTable(array $table)
    {
        $this->arrTable = $table;
    }

    /**
     * get the whole time table
     * @return array
     */
    public function getRawTable()
    {
        if (isset($this->arrTable)) {
            return array(
                'status' => 200,
                'message' => __CLASS__ . ": get table successfully",
                'data' => $this->arrTable
            );
        }
        $ch = curl_init();
        $cookie = $this->getCookie();
        if ($cookie['status'] != 200) {
            return $cookie;
        }
        $urlList = $this->getAvailableUrlList();
        //TODO: 缓存该url，而不是每次都获取
        if ($urlList['status'] != 200) {
            $url = 'http://labsystem.scuec.edu.cn/labcoursearrange2_student.php?labcourse=DXXY-390';
        } else {
            $url = 'http://labsystem.scuec.edu.cn/' . $urlList['data'][0];
        }
        $curlOptions = array(
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => HelperService::DEFAULT_USER_AGENT,
            CURLOPT_REFERER => 'http://labsystem.scuec.edu.cn/left.php',
            CURLOPT_COOKIE => $cookie['data'],
            // CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 7,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false
        );
        curl_setopt_array($ch, $curlOptions);
        /* get the schedule table using the cookie got in the previous request. */
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result == false) {
            // TODO:
            //throw new SchoolInfoException("cannot get the lab schedule table (connect timed out)", 404);
            return [];
        }
        $result = mb_convert_encoding($result, "UTF-8", "gb2312");    //convert the result into utf-8 in order to meet the need of json_encode();
        $matchCount = preg_match_all('/<tr\sbgcolor=#C2C2C2>(.*?)<\/tr>/is', $result, $match);    //match each row of the schedule table
        if ($matchCount == 0) {
            return array(
                'status' => 406,
                'message' => "url modified or account expired",
                'data' => null
            );
            // throw new SchoolInfoException("cannot get the lab schedule table (is url modified?)", 500);
        }
        foreach ($match[1] as $key => $value) {
            preg_match_all('/<td\salign="(center|left)"\svalign="middle">(.*?)<\/td>/is', $value, $arrRow);    //match the table data of each row
            $arrTable[$key] = $arrRow[2];
        }
        $this->arrTable = $arrTable;
        return array(
            'status' => 200,
            'message' => __CLASS__ . ": get table successfully",
            'data' => $arrTable
        );
    }

    /**
     * get a formed message which can send to the user directly.
     * It includes error handling process.
     * @return array
     */
    public function getMessage()
    {
        $arrRawTable = $this->getRawTable();
        if ($arrRawTable['status'] != 200) {
            return $arrRawTable;
        }
        $rawTable = $arrRawTable['data'];
        $resultContentFinished = "";
        $resultContentPending = "";
        $currentWeek = SchoolDatetime::getSchoolWeek();
        foreach ($rawTable as $td) {
            if ($td[1] != '&nbsp;') {
                /* judge if the experiment is confirmed by the teacher */
                $isConfirmed = (strpos($td[2], 'disa')) ? '[不可退选]' : '';
                /* format contents in <td>s */
                $td[0] = str_replace(array('（', '）'), array('[', ']'), $td[0]);
                $str = preg_replace("/地点(\d+)号楼(\d+)室/", "$1#$2", $td[6]);
                if (isset($str)) {
                    $td[6] = $str;
                }
                $week = str_replace('周', '', $td[3]);
                $week = (int)$week;
                $dayInWeek = SchoolDatetime::weekTranslate(trim("星期{$td[4]}"));
                $currentDay = (int)date('N');
                if ($week >= $currentWeek) {
                    if ($week == $currentWeek && $dayInWeek < $currentDay) {
                        /* 已过期的实验 */
                        $resultContentFinished .= "\n"
                            . "名称: {$td[0]}\n"
                            . "时间: {$td[3]} 星期{$td[4]} {$td[5]}\n"
                            . "地点: {$td[6]}\n"
                            . "实验得分/教师: {$td[8]} ({$td[7]})\n";
                    } else {
                        /* 未完成的实验 */
                        $resultContentPending .= "\n"
                            . "名称: {$td[0]}\n"
                            . "时间: {$td[3]} 星期{$td[4]} {$td[5]}\n"
                            . "地点: {$td[6]}\n"
                            . "教师: {$td[7]}  {$isConfirmed}\n";
                    }

                } else {
                    /* 已过期的实验 */
                    $resultContentFinished .= "\n"
                        . "名称: {$td[0]}\n"
                        . "时间: {$td[3]} 星期{$td[4]} {$td[5]}\n"
                        . "地点: {$td[6]}\n"
                        . "实验得分/教师: {$td[8]} ({$td[7]})\n";
                }
            }
        }
        if ($resultContentFinished != "") {
            $resultContentFinished = "  ---- 已完成的实验 ----\n" . $resultContentFinished;
        }
        $title = sprintf("大物实验安排 (第%d周)", $currentWeek);
        $resultContent = trim($resultContentPending . "\n" . $resultContentFinished);
        return [
            'status' => 200,
            'data' => [
                'title' => $title,
                'content' => $resultContent
            ],
        ];
    }

    /**
     * automatically get the URL(s) of the web pages which contain timetable(s)
     * @return array
     */
    public function getAvailableUrlList()
    {
        $cookie = $this->getCookie();
        if ($cookie['status'] !== 200) {
            return $cookie;
        }
        $str = HelperService::httpRequest("http://labsystem.scuec.edu.cn/left_student.php", null, 5, $this->cookie);
        if ($str == false) {
            return array(
                'status' => 404
            );
        }
        $str = mb_convert_encoding($str, 'UTF-8', 'gb2312');
        $matchCount = preg_match_all("/<div class=\"mainDiv\".*?(<\/div>\s*){4}/s", $str, $matches);    //match menus
        if ($matchCount == 0) {
            return array(
                'status' => 406
            );
        }
        $matchCount = preg_match_all("/<a href=\"(.+)\"\s/", $matches[0][1], $urlLists);    //match url lists
        if ($matchCount > 0) {
            return array(
                'status' => 200,
                'message' => __CLASS__ . ': get url lists successfully',
                'data' => $urlLists[1]
            );
        } else {
            return array(
                'status' => 406
            );
        }

    }
}
