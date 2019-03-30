<?php

namespace App\Http\Controllers\Api;

use App\Http\Service\AccountService\Facades\Account;
use App\Http\Service\HelperService;
use App\Http\Service\SchoolDatetime;
use App\Models\StudentInfo;
use EasyWeChat\Kernel\Messages\NewsItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\DomCrawler\Crawler;
use Validator;

class AccountInfoController extends Controller
{
    /**
     * 参考github Restful 状态码设置
     * 200表示账号密码认证通过
     * 422表示认证信息缺失或不正确
     * 401用来表示校验错误
     * 410表示请求资源已不存在，代指学生账号已过期
     * 503表示服务暂不可用，代指Ip被冻结
     * 504表示网关超时，代指学校网站维护.
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
     * constants for setFlag() and getFlag().
     */
    const FLAG_CHECK = 10001;
    const FLAG_LIKE = 10002;

    /**
     * Notes:.
     *
     * @param Request $request 携带账号(username)密码(password）
     *
     * @return array|null|string
     *                           该函数主要用来判断用户是否为在校学生
     *                           以及获取可直接访问办事大厅部分服务的有效cookie
     *                           为了提高效率，使用了Guzzle扩展完成curl操作
     *                           因此返回的cookie为Guzzle扩展中的Cookiejar对象(转化成合法cookie比较耗时，先这样吧
     *                           该对象可经反序列化后直接在Guzzle中使用
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function judgeAccount(Request $request)
    {
        $userInfoArray = $request->toArray();
        $res = HelperService::get('http://id.scuec.edu.cn/authserver/login');
        $data = $res['res']->getBody()->getContents();
        $cookie_jar = $res['cookie'];

        $crawler = new Crawler();
        $crawler->addHtmlContent($data);
        for ($i = 10; $i < 15; ++$i) {
            $key = $crawler->filter('#casLoginForm > input[type="hidden"]:nth-child('.$i.')')
                ->attr('name') ?? null;
            $value = $crawler->filter('#casLoginForm > input[type="hidden"]:nth-child('.$i.')')
                ->attr('value') ?? null;
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
                'message' => '用户账号密码正确',
                'data' => array(
                    'cookie' => serialize($res['cookie']),
                ),
            );

            return $this->response->array($key)->setStatusCode(self::SUCCESS);
        } else {
            $wrong_msg = HelperService::domCrawler($data, 'filter', '#msg'); //登录失败，返回页面错误信息
            switch ($wrong_msg) {
                case '您提供的用户名或者密码有误':
                    $key = array(
                        'status' => self::ACCOUNT_WRONG_PASSWORD,
                        'message' => '你的用户名或密码貌似有误，请重新输入！',
                        'data' => null,
                    );

                    return $this->response->array($key);
                    break;
                case '请输入验证码':
                    $key = array(
                        'status' => self::NEED_CAPTURE,
                        'message' => '你尝试的次数过多，请点击链接进行身份认证后，重新进行绑定！',
                        'data' => null,
                    );

                    return $this->response->array($key);
                    break;
                case 'expired':
                    $key = array(
                        'status' => self::ACCOUNT_EXPIRED,
                        'message' => 'account expired',
                        'data' => null,
                    );

                    return $this->response->array($key);
                    break;
            }
        }
    }

    /**
     * get a formed message which can send to the user directly.
     *
     * @param const int $msgType Here you must specify a message type
     *
     * @return array
     *
     * @throws \App\Exceptions\SchoolInfoException
     */
    public function getTableMessage()
    {
        if (!Account::isBindSSFW()) {
            return "还没有绑定教务系统账号，先去绑定吧：".HelperService::getBindingLink('ssfw');
        }
        $termWeek = SchoolDatetime::getSchoolWeek();    // 本周的学期周数
        if ($termWeek > 20) {   // 如果大于20周课表查询关闭
            $content = "\n本学期已经结束了哦，课表查询功能自动关闭。学霸们，下个学期继续努力吧。";
            $content = HelperService::todyInfo().$content;

            return $content;
        }

        $common = app('wechat_common');
        $openid = $common->openid;
        /* 课表 */
        $account_info_detail = new AccountInfoDetailController();
        $timetable = $account_info_detail->getTimeTable();
        if (!is_array($timetable)) {
            switch ($timetable) {
                case '用户不存在':
                    $news = "绑定账号后即可查询课表。妈妈再也不用担心我忘记教室了。/::,@\n请先".
                        HelperService::getBindingLink('ssfw');
                    break;
                case '用户信息有误':
                    $news = '你绑定的账号信息貌似有误 /:P-( 需要重新'.
                        HelperService::getBindingLink('ssfw');
                    break;
            }

            return $news;
        }
        if (200 == $timetable['status']) {
            /* 课表index以周一为0, so $currDay ranges from 0 to 6 */
            $currDay = intval(date('N')) - 2;
            $currWeek = SchoolDatetime::getSchoolWeek();
            /* 处理课程信息 */
            $currDayTable = $timetable['data']['timetable'][$currDay] ?? [];
            $beginTime = array('08:00', '08:55', '10:00', '10:55', '14:10', '15:05', '16:00', '16:55', '18:40', '19:30', '20:20');
            $replyText = '';
            if (count($currDayTable) > 0) {
                foreach ($currDayTable as $each) {
                    /* 如果课程信息因为不符合格式而无法匹配 */
                    if (isset($each['raw'])) {
                        $replyText .= "\n".$each['raw']."\n";
                        $this->logger->error('invalid course information found', array(
                            'openid' => $openid,
                            'serialized' => serialize($timetable),
                        ));
                    } else {
                        $beginTimeIndex = $each['from_section'] - 1;

                        /* handle cources that have marked as "special" */
                        if (isset($each['special_week'])) {
                            if (0 == $currWeek % 2) {
                                $isOddWeek = false;
                            } else {
                                $isOddWeek = true;
                            }
                            if ((2 == $each['special_week'] && !$isOddWeek) || (1 == $each['special_week'] && $isOddWeek)) {
                                $showSpecialClass = true;
                            } else {
                                $showSpecialClass = false;
                            }
                        }

                        /* 如果本周有这节课 */
                        if ($currWeek >= $each['from_week'] && $currWeek <= $each['to_week']) {
                            if (!isset($each['special_week']) || (isset($each['special_week']) && $showSpecialClass)) {
                                $each['name'] = preg_replace('/\[\d{1,2}\]$/', '', $each['name']);

                                /* 如果是体育课，则显示体育课的时间*/
                                if (preg_match('/^体育[1234]/', $each['name']) && (4 == $beginTimeIndex || 6 == $beginTimeIndex)) {
                                    $PELessonBeginTime = array(
                                        4 => '13:40',
                                        6 => '15:00',
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
                $replyText = '';
            }

            /* 处理调课信息 */
            $adjInfo = isset($timetable['data']['adj_info']) ? $timetable['data']['adj_info'] : [];

            if (isset($adjInfo)) {
                $adjInfoString = '';
                foreach ($adjInfo as $each) {
                    if (isset($each['origin']['raw']) && isset($each['modified']['raw'])) {
                        $adjInfoString .= $each['origin']['raw']."\n=>".$each['modified']['raw']."\n\n";
                    } elseif ((
                            $each['origin']['from_week'] >= $currWeek &&
                            $each['origin']['to_week'] <= $currWeek
                        ) ||
                        (
                            isset($each['modified']['from_week']) && $each['modified']['from_week'] >= $currWeek &&
                            isset($each['modified']['from_week']) && $each['modified']['to_week'] <= $currWeek
                        )
                    ) {
                        $dayIndex = $each['origin']['day_in_week'] - 1;
                        $className = '';
                        foreach ($timetable['data']['timetable'][$dayIndex] as $value) {
                            if ($value['from_section'] == $each['origin']['from_section']) {
                                $className = $value['name'];
                            }
                        }
                        $fromWeekStr = (0 == $each['origin']['to_week'] - $each['origin']['from_week']) ? ($each['origin']['from_week']) : ("{$each['origin']['from_week']}-{$each['origin']['to_week']}");
                        $toWeekStr = (0 == $each['modified']['to_week'] - $each['modified']['from_week']) ? ($each['modified']['from_week']) : ("{$each['modified']['from_week']}-{$each['modified']['to_week']}");

                        $adjInfoString .= "课程名: {$className}\n"
                            ."原上课时间: 第{$fromWeekStr}周,".SchoolDatetime::weekTranslate(intval($each['origin']['day_in_week'])).",第{$each['origin']['from_section']}-{$each['origin']['to_section']}节\n"
                            ."调整后时间: 第{$toWeekStr}周,".SchoolDatetime::weekTranslate(intval($each['modified']['day_in_week'])).",第{$each['modified']['from_section']}-{$each['origin']['to_section']}节\n\n";
                    }
                }
            }
            /* insert course adjustment information */
            if (!empty($adjInfoString)) {
                $adjInfoString = "\n------ 调课信息(仅供参考) ------\n\n".$adjInfoString;
            } else {
                $adjInfoString = '';
            }
            if (0 == strlen($replyText) && 0 == strlen($adjInfoString)) {
                $replyText .= "你今天没有课哦，可以去轻松一下啦 :) \n";

                // BEGIN OF APRIL FOOL CODE BLOCKS
                // temprary codes for April Fool's Day. CAN BE REMOVE ANY TIME, BUT YOU MUST UNCOMMENT THE PREVIOUS LINE!
                //$replyText .= "今天真的没有课，真的，没骗你 :)\n";
                // END OF APRIL FOOL
            }

            /* show the course count of tomorrow */
            $hour = intval(date('G'));
            $nextDayCoursePrompt = '';
            $showPromptThreshold = 19;  //threshold
            if ($hour >= $showPromptThreshold) {
                $nextDay = ($currDay + 1) % 7;
                $targetWeek = $currWeek;
                /* if the next day is Monday, the target week should be the next week. */
                if (0 == $nextDay) {
                    ++$targetWeek;
                }
                $nextDayTimetable = $timetable['data']['timetable'][$nextDay];
                $nextDayCourseCount = count($nextDayTimetable);
                $nextDayCourseName = array();
                if (0 !== $nextDayCourseCount) {
                    foreach ($nextDayTimetable as $each) {
                        if ($targetWeek >= $each['from_week'] && $targetWeek <= $each['to_week']) {
                            if (isset($each['special'])) {
                                if (0 == $targetWeek % 2) {
                                    $isOddWeek = false;
                                } else {
                                    $isOddWeek = true;
                                }
                                if (!((2 == $each['special'] && !$isOddWeek) ||
                                    (1 == $each['special'] && $isOddWeek))) {
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
                if (0 === $nextDayCourseCount) {
                    $nextDayCoursePrompt = "\n明天好像没有课哦～\n\n";
                } else {
                    $nextDayCourseList = '';
                    foreach ($nextDayCourseName as $key => $value) {
                        $value = preg_replace('/\[\d{1,2}\]$/', '', $value);
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
            $updateDateStr = '';
//            if ($lastUpdateStamp !== false) {
//                $updateDateStr = "- 课表更新时间: ".date('Y-m-d H:i', (int)$lastUpdateStamp);
//            }

            $currentSchoolWeek = (int) SchoolDatetime::getSchoolWeek();
            $currentDayChnStr = SchoolDatetime::weekTranslate(intval(date('N')));
            if (0 == $currentSchoolWeek) {
                $dateString = sprintf('%s 课程安排(未开学)', $currentDayChnStr);
            } else {
                $dateString = sprintf('%s 课程安排(第%02d周)', $currentDayChnStr, $currentSchoolWeek);
            }
            // $extraInfo = "\n- 遇到课表错误，记得@程序员反馈哦\n".$updateDateStr;

            // BEGIN OF APRIL FOOL CODE BLOCKS
            $update_time = "课表更新于：".$timetable['update_time'];
           /* $extraInfo = "\n- 要查询所有课程，请查看一周课表\n".$updateDateStr;
            // END OF APRIL FOOL CODE BLOCKS
            $news = [
                new NewsItem(
                    [
                        'title' => $dateString,
                        'description' => $replyText.$adjInfoString.$nextDayCoursePrompt.$update_time.$extraInfo,
                        'url' => config('app.base_url').'/schedule/index.html?openid='.$openid,
                    ]
                ),
            ];*/
            $timeTableLink = '<a href="'.config('app.base_url').'/schedule/index.html?openid='.
                $openid.'">   〖一周课表〗</a>';
            $extraInfo = "\n- 要查询所有课程，请查看".$timeTableLink.$updateDateStr;
            $news = $dateString."\n\n".
                $replyText.$adjInfoString.$nextDayCoursePrompt.$update_time.$extraInfo;
        } else {
            $news = '出现了一些小问题，你可以稍后再试一次。';
        }

        return $news;
    }

    public function getExamMessage()
    {
        /* 考试 */
        $account_info_detail = new AccountInfoDetailController();
        $arrange = $account_info_detail->getExamArrangement();
        if (!is_array($arrange)) {
            switch ($arrange) {
                case '用户不存在':
                    $news = "绑定账号后即可查询考试安排，提前做好复习准备，沉着应对考试。/:,@f\n请先".
                        HelperService::getBindingLink('ssfw');
                    break;
                case '用户信息有误':
                    $news = '你绑定的账号信息貌似有误 /:P-( 需要重新'.
                        HelperService::getBindingLink('ssfw');
                    break;
            }

            return $news;
        }
        $courseCount = count($arrange['data']);
        $content = HelperService::todyInfo()."\n---已安排考试课程---";
        $course = $arrange['data'];
        /*example
         array:4 [▼
          0 => array:12 [▼
            0 => "1"
            1 => "209103013513"
            2 => "网络协议分析与编程"
            3 => "必修课"
            4 => "朱剑林"
            5 => "2.5"
            6 => "67"
            7 => "2018-06-21 16:00-17:40"
            8 => "15号楼-15221"
            9 => "闭卷"
            10 => "笔试"
            11 => "</span>" OR "已结束"
            //未考试时页面表中该项由“ing”变为空，因此之前的正则抓取有bug，先改用结束作为判断标记
          ]
         */
        if ($courseCount > 0) {
            for ($i = 0; $i < $courseCount; ++$i) {
                $time = substr(strip_tags($course[$i][7]), 5); // 时间，截去年份
                $name = $course[$i][2];    // 课程名称
                $content .= "\n".($i + 1).'-['.$course[$i][4].']'.$name;
                if (isset($course[$i][11]) && '已结束' == $course[$i][11]) {
                    $time = substr($time, 0, 5);
                    $content .= '，时间: '.$time.' (已结束)';
                } else {
                    $class = $course[$i][8];  // 考场
                    if (preg_match('/11|15/', $class)) {
                        $class = substr($class, 9); // 去掉楼栋中文
                        $floor = substr($class, 0, 2).'#';
                        $class = substr($class, 2);
                        $class = $floor.$class;
                    }
                    $content .= '，时间: '.$time.'，考场: '.$class.'，座次: '.$course[$i][6];
                }
            }
            $content .= "\n\n考试信息更新于：".$arrange['update_time'];
            $bindingLink = HelperService::getBindingLink('ssfw');
            $content .= "\n\n<a href=\"https://test.stuzone.com/zixunminda-blog/why-extend-cache-time.html\">关于延长缓存时间的更新说明</a>\n想要马上更新考试信息,可以重新".$bindingLink;
        } else {
            $bindingLink = HelperService::getBindingLink('ssfw');
            $content = HelperService::todyInfo()."\n/:sun 目前没有考试安排，平常努力学习才能考出好成绩哦。\n想要马上更新考试信息,可以重新".$bindingLink;
        }

        return $content;
    }

    public function getScoreMessage()
    {
        /* 成绩 */
        $account_info_detail = new AccountInfoDetailController();
        $arrScore = $account_info_detail->getScoreInfo();
        $common = app('wechat_common');
        $openid = $common->openid;
        if (!is_array($arrScore)) {
            switch ($arrScore) {
                case '用户不存在':
                    $news = "绑定账号后即可查询最新的考试成绩。考得好的话不要到处打击人哦。/:,@x\n请先".
                        HelperService::getBindingLink('ssfw');
                    break;
                case '用户信息有误':
                    $news = '你绑定的账号信息貌似有误 /:P-( 需要重新'.
                        HelperService::getBindingLink('ssfw');
                    break;
            }

            return $news;
        }
        switch ($arrScore['status']) {
            case 204:
                $courseStr = SchoolDatetime::getDateInfo()."\n/:sun 过儿，目前还没有成绩出来哦。你可以看看还有什么考试，做好准备才能考出好成绩。你也可以先<a href=\"http://wish.stuzone.com\">去许个愿。</a>";
                app('common_log')->addInfo($arrScore['message']);

                return $courseStr;
            case 205:
                $courseStr = '亲，你可能因尚未评教而无法查询成绩。请使用电脑从办事大厅(http://ehall.scuec.edu.cn/new/index.html'.' )登录教务系统，完成评教后再来查询。不要错过评教时间哦。/:,@-D';
                app('common_log')->addInfo($arrScore['message']);

                return $courseStr;
            case 408:
                $courseStr = '小塔暂时勾搭不到服务器~稍后再试吧>_<';
                app('common_log')->addInfo($arrScore['message']);

                return $courseStr;
        }
        if (1 == $arrScore['flag_likes']) {
            if ($arrScore['is_new']) {
                $items = [
                    new NewsItem(
                        [
                            'title' => '成绩刮刮乐: 您有新成绩!',
                            'description' => '新成绩来啦, 请做好心理准备。回复“关闭刮刮乐”可以直接查看成绩。',
                            'url' => config('app.base_url')."/scratchoff_v6/index.html?ver=7.0&openid={$openid}",
                            'image' => config('app.base_url').'/scratchoff_v6/img/pictures/picture2.png',
                        ]
                    ),
                ];
            } else {
                $items = [
                    new NewsItem(
                        [
                            'title' => '成绩刮刮乐',
                            'description' => '点击图文即可进入成绩刮刮乐。回复"关闭刮刮乐"可以直接查看成绩，回复"刮刮乐"并点击页面下方开关可再次打开该功能',
                            'url' => config('app.base_url')."/scratchoff_v6/index.html?ver=6.0&openid={$openid}",
                            'image' => config('app.base_url').'/scratchoff_v6/img/pictures/picture2.png',
                        ]
                    ),
                ];
            }

            return $items;
        }
        $courseStr = SchoolDatetime::getDateInfo();
        if ($arrScore['is_new']) {
            $courseStr .= "*** 你有新成绩 ***\n\n";
        }
        $courseStr .= "---已出成绩的课程---\n";

        foreach ($arrScore['info'] as $key => $item) {
            if ($item['score'] >= 90 && $item['score'] <= 100) {//成绩90分以上加一朵玫瑰^.^
                $item['score'] = $item['score'].' /:rose';
            }
            $courseStr .= "[{$item['type']}]".$item['name']
                .'，分数: '.$item['score']
                .'，学分: '.$item['credits']
                .'，班级排名: '.$item['rank']."\n";
        }
        $courseStr .= "\n<a href=\"".config('app.base_url')."/scratchoff_v6/index.html?ver=6.0&openid={$openid}\">刮刮乐，玩儿心跳</a>";
        $courseStr .= "\n成绩信息更新于：".$arrScore['update_time'];

        return $courseStr;
    }

    public function guaguale()
    {
        $common = app('wechat_common');
        $openid = $common->openid;
        $redis = Redis::connection('score');
        $redis->hset('user:public:score:flag_likes', $openid, 1);

        return $this->getScoreMessage();
    }

    public function guaguale_close()
    {
        $common = app('wechat_common');
        $openid = $common->openid;
        $redis = Redis::connection('score');
        $redis->hdel('user:public:score:flag_likes', $openid);

        return $this->getScoreMessage();
    }

    public function getMoneyMessage()
    {
        /* 校园卡余额*/
        $account_info_detail = new AccountInfoDetailController();
        $arrMoney = $account_info_detail->getMoney();
        if (!is_array($arrMoney)) {
            switch ($arrMoney) {
                case '用户不存在':
                    $news = "绑定账号后即可查询考试安排，提前做好复习准备，沉着应对考试。/:,@f\n请先".
                        HelperService::getBindingLink('ssfw');
                    break;
                case '用户信息有误':
                    $news = '你绑定的账号信息貌似有误 /:P-( 需要重新'.
                        HelperService::getBindingLink('ssfw');
                    break;
            }

            return $news;
        }
        $contentstr = '校园卡余额为'.$arrMoney['KNYE']."元";
        /*
        if (0 !== $arrMoney['totalAmount']) {
            $contentstr = $contentstr.'当月消费为'.$arrMoney['totalAmount']."元\n其中\n";
            foreach ($arrMoney['consumeType'] as $value) {
                $contentstr = $contentstr.$value."元\n";
            }
        } else {//当月没有消费记录
            $contentstr = $contentstr.'本月还没有消费记录噢~';

            return $contentstr;
        }
        $contentstr = $contentstr.'查询校园卡消费明细请回复"校园卡消费详情"';
        */
        $temp = "\n余额查询功能测试中，当前结果仅供参考噢~";
        $contentstr .= $temp;
        return $contentstr;
    }

    /**
     * Notes:为前端提供api接口.
     *
     * @param $openid
     */
    public function tableApi($openid)
    {
        $matchCount = preg_match(config('app.openid_regex'), $openid, $match);
        if (true == $matchCount) {
            $openid = $match[0];
            $redis = Redis::connection('timetable');
            $timetable_cache = $redis->get('timetable_'.$openid);
            if (false !== $timetable_cache) {
                $arrTimetable = json_decode($timetable_cache, true);
                $curWeek = SchoolDatetime::getSchoolWeek();
                // 如果没开学，则显示第一周课表
                $curWeek = $curWeek ? $curWeek : 1;
                $arrTimetable['current_week'] = $curWeek;
                $retArray = array(
                    'status' => 200,
                    'data' => $arrTimetable,
                );
            } else {
                $retArray = array(
                    'status' => 404,
                    'data' => null,
                );
            }
        } else {
            $retArray = array(
                'status' => 405,
                'data' => null,
            );
        }
        echo json_encode($retArray);
    }

    /**
     * Notes:为前端提供api接口.
     *
     * @param $openid
     * @param Request $request
     */
    public function scoreApi($openid, Request $request)
    {
        $matchCount = preg_match(config('app.openid_regex'), $openid, $match);
        if (true == $matchCount) {
            $openid = $match[0];
            $redis = Redis::connection('score');
            if (empty($request->act)) {
                $score_cache = $redis->get('score_'.$openid);
                //将json转换为前端需要的格式
                $new_score_array = json_decode($score_cache, true);
                if (!$new_score_array)
                    $new_score_array = [];
                $checked_score_array = $redis->lRange("user:{$openid}:score:checked", 0, -1);
                $black_score_array = $redis->hgetall("user:{$openid}:score:hidden");
                foreach ($new_score_array as $key => $value) {
                    $value['hidden'] = key_exists($value->name, $black_score_array) ? true : false;
                    $value['is_checked'] = in_array($value->name, $checked_score_array) ? true : false;
                }
                $new_score_array = array('status' => 200, 'data' => $new_score_array);
                echo json_encode($new_score_array);
            } else {
                switch ($request->act) {
                    case 'checked':
                        $redis->rPush("user:{$openid}:score:checked", $request->data);
                        $redis->lTrim("user:{$openid}:score:checked", 0, 30);
                        break;
                    case 'black':
                        if ($request->discard) {
                            echo $redis->hDel("user:{$openid}:score:hidden", $request->course_name);
                        } else {
                            echo $redis->hSet("user:{$openid}:score:hidden", $request->course_name, 1);
                        }
                        break;
                    case 'like':
                        if ('false' == $request->data) {
                            echo $redis->hSet('user:public:score:flag_likes', $openid, 0);
                        } elseif ('true' == $request->data) {
                            echo $redis->hSet('user:public:score:flag_likes', $openid, 1);
                        }
                        break;
                }
            }
        }
    }

    /**
     * 返回账户是否绑定
     *
     * @param Request $request
     * @return array
     */
    public function isBind(Request $request)
    {
        $validator = Validator::make($request->input(), [
            'openid' => 'required'
        ]);
        if ($validator->fails()) {
            $this->response->errorBadRequest('parameter missing');
        }
        $openid = $request->get('openid');
        $user = StudentInfo::where('openid', $openid)->first();
        if (!$user || !$user->account) {
            return [
                'bind' => 0
            ];
        }
        return [
            'bind' => 1,
            'account' => $user->account
        ];
    }
}
