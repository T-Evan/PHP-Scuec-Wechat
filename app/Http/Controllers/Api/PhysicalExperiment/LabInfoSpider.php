<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 2018/10/24
 * Time: 5:37 PM
 */

namespace App\Http\Controllers\Api\PhysicalExperiment;


use App\Http\Controllers\Api\AccountManager\Exceptions\AccountNotBoundException;
use App\Http\Controllers\Api\AccountManager\Exceptions\AccountValidationFailedException;
use App\Http\Controllers\Api\AccountManager\LabSysAccountManager;
use App\Http\Service\HelperService;
use App\Http\Service\SchoolDatetime;
use App\Models\StudentInfo;

class LabInfoSpider
{
    protected $labInfoArray;
    protected $username;
    protected $password;
    protected $studentInfo;
    protected $openid;

    function __construct(StudentInfo $studentInfo)
    {
        $this->username = $studentInfo->account;
        $this->password = $studentInfo->lab_password;
        $this->openid   = $studentInfo->openid;
    }

    /**
     * get the whole time table
     * @return array
     * @throws AccountNotBoundException
     * @throws AccountValidationFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getRawTable()
    {
        if (isset($this->labInfoArray)) {
            return array(
                'status' => 200,
                'message' => __CLASS__ . ": get table successfully",
                'data' => $this->labInfoArray
            );
        }
        $labAccountManager = new LabSysAccountManager();
        $cookieJar = $labAccountManager->getCookie($this->openid);
        $labInfoResponse = HelperService::get(
            'http://labsystem.scuec.edu.cn/labcoursearrange2_student.php?labcourse=DXXY-392',
            $cookieJar,
            'http://labsystem.scuec.edu.cn/left.php'
        );
        $labInfoDom = $labInfoResponse['res']->getBody()->getContents();
        //convert the result into utf-8 in order to meet the need of json_encode();
        $labInfoDom = mb_convert_encoding($labInfoDom, "UTF-8", "gb2312");
        //match each row of the schedule table
        $matchCount = preg_match_all('/<tr\sbgcolor=#C2C2C2>(.*?)<\/tr>/is', $labInfoDom, $labInfoRows);
        if ($matchCount == 0) {
            return array(
                'status' => 406,
                'message' => "未查询到你的物理实验",
                'data' => null
            );
            // throw new SchoolInfoException("cannot get the lab schedule table (is url modified?)", 500);
        }
        $this->labInfoArray = [];
        foreach ($labInfoRows[1] as $key => $value) {
            //match the table data of each row
            preg_match_all('/<td\salign="(center|left)"\svalign="middle">(.*?)<\/td>/is', $value, $arrRow);
            $this->labInfoArray[$key] = $arrRow[2];
        }
        return array(
            'status' => 200,
            'message' => __CLASS__ . ": get table successfully",
            'data' => $this->labInfoArray
        );
    }

    /**
     * get a formed message which can send to the user directly.
     * It includes error handling process.
     * @return array
     * @throws AccountNotBoundException
     * @throws AccountValidationFailedException
     * @throws \GuzzleHttp\Exception\GuzzleException
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
}
