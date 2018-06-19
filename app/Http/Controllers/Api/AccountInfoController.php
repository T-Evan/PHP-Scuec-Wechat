<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\StudentsController;
use App\Http\Service\HelperService;
use App\Http\Service\SchoolDatetime;
use EasyWeChat\Kernel\Messages\NewsItem;
use Illuminate\Http\Request;
use Symfony\Component\DomCrawler\Crawler;

class AccountInfoController extends Controller
{
    /**
     * 参考github Restful 状态码设置
     * 200表示账号密码认证通过
     * 422表示认证信息缺失或不正确
     * 401用来表示校验错误
     * 410表示请求资源已不存在，代指学生账号已过期
     * 503表示服务暂不可用，代指Ip被冻结
     * 504表示网关超时，代指学校网站维护
     */

    const ACCOUNT_WRONG_PASSWORD = 422;
    const NEED_CAPTURE = 401;
    const ACCOUNT_EXPIRED = 410;
    const TIMEOUT = 504;
    const FREEZE = 503;
    const SUCCESS = 200;
    const TIMETABLE = 10001;
    const EXAM_SCORE = 10002;
    const EXAM_ARRANGEMENT = 10003;

    /**
     * Notes:
     * @param Request $request 携带账号(username)密码(password）
     * @return array|null|string
     * 该函数主要用来判断用户是否为在校学生
     * 以及获取可直接访问办事大厅部分服务的有效cookie
     * 为了提高效率，使用了Guzzle扩展完成curl操作
     * 因此返回的cookie为Guzzle扩展中的Cookiejar对象(转化成合法cookie比较耗时，先这样吧
     * 该对象可经反序列化后直接在Guzzle中使用
     */

    public function judgeAccount(Request $request)
    {
        $userInfoArray=$request->toArray();
        $res = HelperService::get('http://id.scuec.edu.cn/authserver/login');
        $data = $res['res']->getBody()->getContents();
        $cookie_jar= $res['cookie'];

        $crawler = new Crawler();
        $crawler->addHtmlContent($data);
        for ($i = 10; $i < 15; $i++) {
            $key = $crawler->filter('#casLoginForm > input[type="hidden"]:nth-child(' . $i . ')')
                ->attr('name');
            $value = $crawler->filter('#casLoginForm > input[type="hidden"]:nth-child(' . $i . ')')
                ->attr('value');
            $userInfoArray[$key] = $value;
        }

        $res = HelperService::post(
            $userInfoArray,
            'http://id.scuec.edu.cn/authserver/login?goto=http%3A%2F%2Fssfw.scuec.edu.cn%2Fssfw%2Fj_spring_ids_security_check',
            'form_params',
            'http://ehall.scuec.edu.cn/new/index.html',
            $cookie_jar
        );

        $data = $res['res']->getBody()->getContents();
        $user_name = HelperService::domCrawler($data, 'filterXPath', '//*[@class="auth_username"]/span/span'); //尝试从登录后页面获取姓名，判断是否登录成功
        if ($user_name) {
            $key = array(
                'message' => "用户账号密码正确",
                'data' => array(
                    'cookie' => serialize($res['cookie'])
                )
            );
            return $this->response->array($key)->setStatusCode(self::SUCCESS);
        } else {
            $wrong_msg = HelperService::domCrawler($data, 'filter', '#msg'); //登录失败，返回页面错误信息
            switch ($wrong_msg) {
                case '您提供的用户名或者密码有误':
                    $key = array(
                        'status' => self::ACCOUNT_WRONG_PASSWORD,
                        'message' => "你的用户名或密码貌似有误，请重新输入！",
                        'data' => null
                    );
                    return $this->response->array($key);
                    break;
                case '请输入验证码':
                    $key = array(
                        'status' => self::NEED_CAPTURE,
                        'message' => "你尝试的次数过多，请点击链接进行身份认证后，重新进行绑定！",
                    'data' => null
                    );
                    return $this->response->array($key);
                    break;
                case 'expired':
                    $key = array(
                        'status' => self::ACCOUNT_EXPIRED,
                        'message' => "account expired",
                        'data' => null
                    );
                    return $this->response->array($key);
                    break;
            }
        }
    }

    /**
     * get a formed message which can send to the user directly.
     * @param const int $msgType Here you must specify a message type.
     * @return array
     * @throws \App\Exceptions\SchoolInfoException
     */
    public function getMessage($msgType = null)
    {
        $message = app('wechat')->server->getMessage();
        $openid = $message['FromUserName'];
        /* 课表 */
        if (!isset($msgType) || $msgType == self::TIMETABLE) {
            $account_info_detail=new AccountInfoDetailController();
            $timetable = $account_info_detail->getTimeTable();
            if (!is_array($timetable)) {
                switch ($timetable) {
                    case '用户不存在':
                        $news = "你还没有绑定账号噢~ /:P-( ".
                            HelperService::getBindingLink($openid, 'ssfw');
                        break;
                    case '用户信息有误':
                        $news = "你绑定的账号信息貌似有误 /:P-( 需要重新".
                            HelperService::getBindingLink($openid, 'ssfw');
                        break;
                }
                return $news;
            }
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
                                "openid" => $openid,
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
                if (!empty($adjInfoString)) {
                    $adjInfoString = "\n------ 调课信息(仅供参考) ------\n\n".$adjInfoString;
                }else{
                    $adjInfoString='';
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
                            'url'       => config('app.base_url').'/lab_query/web/schedule/index.html?openid='.$openid,
                        ]
                    )
                ];
            } else {
                $news = "出现了一些小问题，你可以稍后再试一次。";
            }
            return $news;
        } /* 考试 */
        elseif ($msgType == self::EXAM_ARRANGEMENT) {
            $arrange = $this->getTestArrangement();
            if ($arrange['status'] != 200) {
                return $arrange;
            }
            $replyText = "";
            if (count($arrange['data']) > 0) {
                foreach ($arrange['data'] as $key => $item) {
                    $displayNum = $key + 1;
                    $replyText .= "[{$displayNum}]\n"
                        ."科目: ".$item[2]."\n"
                        ."时间: ".$item[7]."\n"
                        ."地点: ".$item[8]."\n"
                        ."教师: ".$item[4]."\n\n";
                }
            } else {
                $replyText = "你当前没有考试安排哦~";
            }
            $items = [
                new NewsItem(
                    [
                        'title'       => '考试安排',
                        'description' => $replyText,
                    ]
                )
            ];
            return $items;
        } /* 成绩 */
        elseif ($msgType == self::EXAM_SCORE) {
            $arrScore = $this->getScore();
            if ($arrScore['status'] != 200) {
                return $arrScore;
            }
            $replyText = '';
            foreach ($arrScore['data'] as $key => $item) {
                $replyText .= "课程：[{$item[4]}]".$item[3]."\n"
                    ."分数: ".$item[7]."\n\n";
            }
            $items = [
                new NewsItem(
                    [
                        'title'       => '我的成绩',
                        'description' => $replyText,
                    ]
                )
            ];
            return $items;
        } else {
            return array('status' => 404, 'message' => __CLASS__ . ': Exception occured', 'data' => null );
        }
    }


}
