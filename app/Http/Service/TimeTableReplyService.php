<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/19
 * Time: 2:39
 */

namespace App\Http\Service;

use App\Http\Controllers\Api\AccountInfoController;
use EasyWeChat\Kernel\Messages\NewsItem;
use Illuminate\Support\Facades\Log;

class TimeTableReplyService
{
    const BASE_URL = 'https://wechat.stuzone.com/iscuecer';

    private $openid;
    private $objAcademyInfo;
    private $logger;

    public function __construct($openid, $username=null, $password=null)
    {
        $this->openid = $openid;
        $this->objAcademyInfo = new AccountInfoController();
        $this->logger = new Log();
    }

    public  function test(){
        $timetable = $this->objAcademyInfo->getTimeTable($this->openid);
        $items = [
            new NewsItem(
                [
                    'title'       => '小塔的校园网使用说明书',
                    'description' => '校园网的秘密都在这里了~',
                    'url'       => 'http://mp.weixin.qq.com/s?__biz=MzA5OTA0ODUyOA==&mid=400024069'.
                        '&idx=1&sn=58c58c242113fdeeb86ea575ac997d7d&scene=4#wechat_redirect',
                    'image' => config('app.base_url').'/img/wifi.jpg',
                ]
            )
        ];
        return $items;
    }
    /**
     * @throws \Exception
     */
    public function reply()
    {
//        $cache = CacheCreator::getInstance();
//        $cachedTimetable = $cache->get('timetable:'.$this->openid);
//        if ($cachedTimetable !== false) {
//            $timetable = array(
//                'status' => 200,
//                'data' => json_decode($cachedTimetable, true)
//            );
//            $this->logger->debug('user:'.$this->openid.', table got');
//            unset($cachedTimetable);

//        if ($this->openid=='1') {
//        } else {
//            $timetable = $this->objAcademyInfo->getTimeTable($this->openid, '2017-2018', 2);
//            if ($timetable['status'] == 200) {
//                $cache->set('timetable:'.$this->openid, json_encode($timetable['data']));
//                $cache->persist('timetable:'.$this->openid);
//                $cache->hSet('timetable:refresh_timestamp', $this->openid, time());
//            }
//        }
        $timetable = $this->objAcademyInfo->getTimeTable($this->openid);
        if ($timetable['status'] == 200) {
            date_default_timezone_set(config('app.timeZone'));
            /* 课表index以周一为0, so $currDay ranges from 0 to 6 */
            $currDay = intval(date('N')) - 1;
            $currWeek = SchoolDatetime::getSchoolWeek();
            /* 处理课程信息 */
            $currDayTable = $timetable['data']['timetable'][$currDay];
            $beginTime = array("08:00", "08:55", "10:00", "10:55", "14:10", "15:05", "16:00", "16:55", "18:40", "19:30", "20:20");
            $replyText = "";
            if (count($currDayTable) > 0) {
                foreach ($currDayTable as $each) {
                    /* 如果课程信息因为不符合格式而无法匹配 */
                    if (isset($each['raw'])) {
                        $replyText .= "\n".$each['raw']."\n";
                        $this->logger->error("invalid course information found", array(
                            "openid" => $this->openid,
                            "serialized" => serialize($timetable)
                        ));
                    } else {
                        $beginTimeIndex = $each['from_section'] - 1;

                        /* handle cources that have marked as "special" */
                        if (isset($each['special_week'])) {
                            if ($currWeek % 2 == 0) {
                                $isOddWeek = false;
                            } else {
                                $isOddWeek = true;
                            }
                            if (($each['special_week'] == 2 && !$isOddWeek) || ($each['special_week'] == 1 && $isOddWeek)) {
                                $showSpecialClass = true;
                            } else {
                                $showSpecialClass = false;
                            }
                        }

                        /* 如果本周有这节课 */
                        if ($currWeek >= $each['from_week'] && $currWeek <= $each['to_week']) {
                            if (!isset($each['special_week']) || (isset($each['special_week']) && $showSpecialClass)) {
                                $each['name'] = preg_replace('/\[\d{1,2}\]$/', "", $each['name']);

                                /* 如果是体育课，则显示体育课的时间*/
                                if (preg_match('/^体育[1234]/', $each['name']) && ($beginTimeIndex == 4 || $beginTimeIndex == 6)) {
                                    $PELessonBeginTime = array(
                                        4 => '13:40',
                                        6 => '15:00'
                                    );
                                    $lessonBeginTimeStr = $PELessonBeginTime[$beginTimeIndex];
                                } else {
                                    $lessonBeginTimeStr = $beginTime[$beginTimeIndex];
                                }

                                $replyText .= "---- {$lessonBeginTimeStr} ----\n"
                                    ."课程名: {$each['name']}\n"
                                    ."时间: {$each['from_week']}-{$each['to_week']}周, {$each['from_section']}-{$each['to_section']}节\n"
                                    ."地点: {$each['place']}\n"
                                    ."教师: {$each['teacher']}\n\n";
                            }
                        } else {
                            continue;
                        }
                    }
                }
            } else {
                $replyText = "";
            }

            /* 处理调课信息 */
            $adjInfo = $timetable['data']['adj_info'];

            if (isset($adjInfo)) {
                $adjInfoString = "";
                foreach ($adjInfo as $each) {
                    if (isset($each['origin']['raw']) || isset($each['modified']['raw'])) {
                        $adjInfoString .= $each['origin']['raw']."\n=>".$each['modified']['raw']."\n\n";
                    } elseif ((
                            $each['origin']['from_week'] >= $currWeek &&
                            $each['origin']['to_week'] <= $currWeek
                        ) ||
                        (
                            $each['modified']['from_week'] >= $currWeek &&
                            $each['modified']['to_week'] <= $currWeek
                        )
                    ) {
                        $dayIndex = $each['origin']['day_in_week'] - 1;
                        $className = "";
                        foreach ($timetable['data']['timetable'][$dayIndex] as $value) {
                            if ($value['from_section'] == $each['origin']['from_section']) {
                                $className = $value['name'];
                            }
                        }
                        $fromWeekStr = ($each['origin']['to_week']-$each['origin']['from_week'] == 0) ? ($each['origin']['from_week']) : ("{$each['origin']['from_week']}-{$each['origin']['to_week']}");
                        $toWeekStr = ($each['modified']['to_week']-$each['modified']['from_week'] == 0) ? ($each['modified']['from_week']) : ("{$each['modified']['from_week']}-{$each['modified']['to_week']}");

                        $adjInfoString .= "课程名: {$className}\n"
                            ."原上课时间: 第{$fromWeekStr}周,".SchoolDatetime::weekTranslate(intval($each['origin']['day_in_week'])).",第{$each['origin']['from_section']}-{$each['origin']['to_section']}节\n"
                            ."调整后时间: 第{$toWeekStr}周,".SchoolDatetime::weekTranslate(intval($each['modified']['day_in_week'])).",第{$each['modified']['from_section']}-{$each['origin']['to_section']}节\n\n";
                    }
                }
            }
            /* insert course adjustment information */
            if ($adjInfoString != "") {
                $adjInfoString = "\n------ 调课信息(仅供参考) ------\n\n".$adjInfoString;
            }
            if (strlen($replyText) == 0 && strlen($adjInfoString) == 0) {
                $replyText .= "你今天没有课哦，可以去轻松一下啦 :) \n";

                // BEGIN OF APRIL FOOL CODE BLOCKS
                // temprary codes for April Fool's Day. CAN BE REMOVE ANY TIME, BUT YOU MUST UNCOMMENT THE PREVIOUS LINE!
                //$replyText .= "今天真的没有课，真的，没骗你 :)\n";
                // END OF APRIL FOOL
            }

            /* show the course count of tomorrow */
            $hour = intval(date('G'));
            $nextDayCoursePrompt = "";
            $showPromptThreshold = 19;  //threshold
            if ($hour >= $showPromptThreshold) {
                $nextDay = ($currDay + 1) % 7;
                $targetWeek = $currWeek;
                /* if the next day is Monday, the target week should be the next week. */
                if ($nextDay == 0) {
                    ++$targetWeek;
                }

                $nextDayTimetable = $timetable['data']['timetable'][$nextDay];
                $nextDayCourseCount = count($nextDayTimetable);
                $nextDayCourseName = array();
                if ($nextDayCourseCount !== 0) {
                    foreach ($nextDayTimetable as $each) {
                        if ($targetWeek >= $each['from_week'] && $targetWeek <= $each['to_week']) {
                            if (isset($each['special'])) {
                                if ($targetWeek % 2 == 0) {
                                    $isOddWeek = false;
                                } else {
                                    $isOddWeek = true;
                                }
                                if (!(($each['special'] == 2 && !$isOddWeek) || ($each['special'] == 1 && $isOddWeek))) {
                                    --$nextDayCourseCount;
                                } else {
                                    /* get the course name */
                                    $nextDayCourseName[] = $each['name'];
                                }
                            } else {
                                /* get the course name */
                                $nextDayCourseName[] = $each['name'];
                            }
                        } else {
                            --$nextDayCourseCount;
                        }
                    }
                }
                /* if the count is 0 at first, or is deducted to 0 */
                if ($nextDayCourseCount === 0) {
                    $nextDayCoursePrompt = "\n明天好像没有课哦～\n\n";
                } else {
                    $nextDayCourseList = "";
                    foreach ($nextDayCourseName as $key => $value) {
                        $value = preg_replace('/\[\d{1,2}\]$/', "", $value);
                        $nextDayCourseList .= sprintf("%d) %s\n", $key + 1, $value);
                    }
                    $nextDayCourseList = "\n------ 明天的课程 ------\n\n".$nextDayCourseList;
                    if ($hour >= 19 && $hour <= 21) {
                        $nextDayCoursePrompt = "(ง •̀_•́)ง  明天有{$nextDayCourseCount}门课。";
                    } elseif ($hour >= 22) {
                        $nextDayCoursePrompt = "学霸君,明天还有{$nextDayCourseCount}门课,今晚早点休息,晚安~";
                    }
                    $nextDayCoursePrompt = $nextDayCourseList."\n".$nextDayCoursePrompt."\n";
                }
            }

            /* show last-update time of the timetable */
//            $lastUpdateStamp = $cache->hGet('timetable:refresh_timestamp', $this->openid);
            $updateDateStr = "";
//            if ($lastUpdateStamp !== false) {
//                $updateDateStr = "- 课表更新时间: ".date('Y-m-d H:i', (int)$lastUpdateStamp);
//            }

            $currentSchoolWeek = (int)SchoolDatetime::getSchoolWeek();
            $currentDayChnStr =  SchoolDatetime::weekTranslate(intval(date('N')));
            if ($currentSchoolWeek == 0) {
                $dateString = sprintf("%s 课程安排(未开学)", $currentDayChnStr);
            } else {
                $dateString = sprintf("%s 课程安排(第%02d周)", $currentDayChnStr, $currentSchoolWeek);
            }
            // $extraInfo = "\n- 遇到课表错误，记得@程序员反馈哦\n".$updateDateStr;

            // BEGIN OF APRIL FOOL CODE BLOCKS
            $extraInfo = "\n- 要查询所有课程，请查看一周课表\n".$updateDateStr;
            // END OF APRIL FOOL CODE BLOCKS
            $news = [
                new NewsItem(
                    [
                        'title'       => $dateString,
                        'description' => $replyText.$adjInfoString.$nextDayCoursePrompt.$extraInfo,
                        'url'       => config('app.base_url').'/lab_query/web/schedule/index.html?openid='.$this->openid,
                    ]
                )
            ];
        } elseif ($timetable['status'] === 40003) {
            $news = "你绑定的账号信息貌似有误 /:P-( 需要重新".'<a href="'.self::BASE_URL.'/binding/login.php?type=ssfw&openid='.$this->openid.'">〖绑定账号〗</a>';
        } elseif ($timetable['status'] === 50005) {
            $news = '暂时勾搭不到服务器噢，请稍后再来吧~<a href="https://test.stuzone.com/zixunminda-blog/why-extend-cache-time.html">关于延长缓存时间的更新说明</a>';
        } elseif ($timetable['status'] === 40006) {
            $news = "认证失败了噢，请重新登录认证系统验证后再来绑定吧~\n".'<a href="http://id.scuec.edu.cn/authserver/login">〖统一身份认证〗</a>';
        } else {
            $news = "出现了一些小问题，你可以稍后再试一次。".$timetable['status'];
            $this->logger->error("unknown error", $timetable);
        }
//        $this->logger->debug('user:'.$this->openid.' timetable returned from'.__CLASS__);
        return $news;
    }
}
