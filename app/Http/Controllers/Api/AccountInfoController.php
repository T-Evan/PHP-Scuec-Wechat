<?php

namespace App\Http\Controllers\Api;

use App\Http\Service\HelperService;
use App\Http\Service\SchoolDatetime;
use EasyWeChat\Kernel\Messages\NewsItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
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
     * @param $userInfoArray:携带账号密码的数组
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
                        'message' => "你输入错误的次数过多，请尝试登陆官网认证身份后，重新进行绑定！",
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

    public function getTimeTable($openid, $year = null, $term = null, $debug = false)
    {
        $key = 'ssfw'.'_'.$openid;
        $res = Redis::get($key);
        $cookie = unserialize($res);
        $res = HelperService::get(
            'http://id.scuec.edu.cn/authserver/login?goto=http%3A%2F%2Fssfw.scuec.edu.cn%2Fssfw%2Fj_spring_ids_security_check',
            $cookie,
            'http://ssfw.scuec.edu.cn/ssfw/index.do'
        );
        $jar1 = $res['cookie'];
        $res = HelperService::get(
            'http://ssfw.scuec.edu.cn/ssfw/pkgl/kcbxx/4/2017-2018-2.do?flag=4&xnxqdm=2017-2018-2',
            $jar1,
            'http://ssfw.scuec.edu.cn/ssfw/index.do'
        );
        $table_html = $res['res']->getbody();

        /*
         * 处理课表，抽出不带“未安排课程”子表格的html代码段（这样写比正则匹配快多了[当然可能是我正则写的烂）
         * 值得注意的是，有些人没有“未安排课程子表”。
         */
        $trimedTable = strstr($table_html, "=\"CourseFormTab");
        if (strpos($trimedTable, "NoFitCourse") !== false) {
            $nofitTable = strstr($trimedTable, "NoFitCourse");//抽出“未安排课程”之后的html代码段
            $trimedTable = strstr($trimedTable, "NoFitCourse", true);
        } else {
            $trimedTable = strstr($trimedTable, "</table>", true);
        }
        unset($rawTimetable);


        /* match and parse curriculum adjustment information */
        /* 匹配并解析调课信息 */
        $currAdjInfo = array();
        $matchCount = preg_match_all("/<img.+?tkid=.+?id='[\d-]+'\s?>\[调课\](.+?);--><br>(.*?);<\/div>/", $trimedTable, $matches);

        if ($matchCount >= 0) {
            /* $matches 为初结果 */
            $currAdjInfo = $this->parseAdjustmentInfo($matches);
        } else {
            $currAdjInfo = null;
        }
        unset($matches);
        /* match timetable */
        $trimedTable = preg_replace("/<img.+?<\/div>/s", "", $trimedTable);    //去除调课信息

        /* 匹配tr标签 */
        preg_match_all("/<tr><td.+?<\/tr>/s", $trimedTable, $arrTr);
        preg_match_all("/<tr><td.+?<\/tr>/s", $nofitTable, $nofitarrTr);
        $nofitarrTr=array_flatten($nofitarrTr);
        $arrTr=array_flatten($arrTr);

        /*匹配未安排课程不包括html标签的剩余部分，每节课和内容存储在二维数组中（实际生成的为三维数组，查完函数用法后要记得来修复）*/
        if (isset($nofitarrTr[0])) {//如果有未安排课程
            foreach ($nofitarrTr as $key => $value) {
                preg_match_all("/[\x80-\xff].+?(?=<\/td>)|[1-16].+?(?=<\/td>)/s", $nofitarrTr[$key], $nofitarr[$key]);
            }
            /* 解析的结果会被存入$no_arrange内。*/
            $no_arrange= array();
            foreach ($nofitarr[0][0] as $key => $value) {
                preg_match_all("/(\d{1,2})/", $nofitarr[$key][0][1], $week);//匹配上课时间
                preg_match("/.+(?=&)/", $nofitarr[$key][0][0], $name);//匹配课程名字
                $no_arrange[$key] = array(
                    'name' => $name[0],
                    'teacher' => $nofitarr[$key][0][1]
                );//构造数组
            }


            /* 解析教务系统课程表。
             * 以下代码可以实现：将每天的课程放到对应的数组里（星期一=>[0], 星期二=>[1], etc.）。
             * 解析的难度在于，课表会使用rowspan属性来控制单个课程占据行单元格的数量。因此，每行的td个数
             * 不定，其数量受到上一行课程的影响（占据下行的单元格）。
             * 如果在解析每行时，将该特定行对下行造成的影响（占据单元格的数量）记录下来，就可以在解析下一行时，
             * 实现对td的定位（即确认下行的某个td输入本周的第几天），
             */
            /*
             * $skipCount 用于对课程超出长度的计数(即解析往后的行时应跳过的节数)，每个元素对应一周的每一天。
             * for example, 如果第一天第一次课为1-3节，那么在解析第一行后，$skipCount[0] == 2。当解析
             * 行时，会将此值减一。该值会在解析的过程中被累加。
             */
            /* 解析的结果会被存入$timetable内。*/
            $skipCount = array(0,0,0,0,0,0,0);
            $timetable = array();

            /* 遍历所有行(<tr>) */
            foreach ($arrTr as $trNum => $row) {
                /*
                 * 匹配td标签。其实开头有两个不属于课表内容的td(一个空td和一个表头td)，但是该正则表达式
                 * 匹配不到它们。
                 */
                $matchCount = preg_match_all("/<td colspan=\"?\d+\"?\srowspan=\"(\d+?)\"\s?>(.+?)<\/td>/s", $row, $arrTd);
                if ($matchCount == 0) {
                    // 经检查有些tr标签内的确没有课程（这种课表的特征是tr标签内只有1个表示第几节的td标签），不能被匹配，因此不将其作为异常来处理
                    if (preg_match('/<td/', $row) == 1) {
                        for ($i=0; $i < 7; $i++) {
                            $skipCount[$i] --;
                        }
                        continue;
                    } else {
                        throw new SchoolInfoException("cannot match any td tag", 500, array(
                        'timetable' => base64_encode($trimedTable),
                        'tr' => base64_encode($row)
                    ));
                    }
                }

                $tdNum = 0;
                /*
                 * 下面的循环用于将本行的课程放置于$timetable对应的位置；位置通过$skipCount的值来判断。
                 * 若对应列的计数不为零，即本行的此单元格被上行所占，本行中的<td>不应属于该列。因此应将对应
                 * 计数减一，并跳过该列，即continue; 若对应值为0, 则读入本行一个<td>的内容，并写入$timetable.
                 * 需要注意的是，第0行一定有7个<td>.
                 * 变量$i指的是星期（0为周一，1为周二，etc.）
                 */
                for ($i=0; $i<7; $i++) {
                    if ($skipCount[$i] != 0) {
                        $skipCount[$i]--;
                        continue;
                    } else {
                        $fromSection = $trNum + 1;
                        /* 处理<td>标签中，使用<hr>分割的位置冲突课程, 如果有<hr>，则分割解析后再存入。 */
                        if (strpos($arrTd[2][$tdNum], "<hr>") !== false) {
                            $hrClass = explode("<hr>", $arrTd[2][$tdNum]);
                            foreach ($hrClass as $eachClass) {
                                $timetable[$i][] = array(
                                $fromSection,
                                trim(HelperService::removeHtmlEntities(strip_tags($eachClass, "<br>")))
                                );
                            }
                        } else {
                            $timetable[$i][] = array(
                            $fromSection,
                            trim(HelperService::removeHtmlEntities(strip_tags($arrTd[2][$tdNum], "<br>"))),
                            );
                        }
                        /* 将新累积的单元格数量(rowspan)累加到$skipCount数组中，并使$tdNum指向下一个td */
                        $skipCount[$i] += intval($arrTd[1][$tdNum]) - 1;
                        $tdNum++;
                    }
                }
            }
            $parsedTimetable = $this->parseTimetable($timetable);
            $rtnArray = array(
            'status' => 200,
            'message' => __CLASS__ . ': get timetable successfully',
            'data' => array(
                'adj_info' => $currAdjInfo,
                'timetable' => $parsedTimetable,
                 )
             );
            //添加未安排课程信息
            if (isset($no_arrange)) {
                $rtnArray['data']['no_arrange']=$no_arrange;
            }
            if ($debug == true) {
                $rtnArray['raw_timetable'] = $trimedTable;
            }
            return $rtnArray;
        }
    }

    /**
     * get a formed message which can send to the user directly.
     * @param const int $msgType Here you must specify a message type.
     * @return array
     */
    public function getMessage($msgType = null ,$openid)
    {
        /* 课表 */
        if (!isset($msgType) || $msgType == self::TIMETABLE) {
            $timetable = $this->getTimeTable($openid);
            if ($timetable['status'] != 200) {
                return $timetable;
            }
            date_default_timezone_get(config('app.timeZone'));
            /* 课表index以周一为0 */
            $currDay = intval(date('N')) - 1;

            $currWeek = SchoolDatetime::getSchoolWeek();

            /* 处理课程信息 */
            $currTable = $timetable['data']['timetable'][$currDay];
            $beginTime = array("08:00", "08:55", "10:00", "10:55", "14:10", "15:05",
                "16:00", "16:55", "18:40", "19:30", "20:20");
            $replyText = "";
            if (count($currTable) > 0) {
                foreach ($currTable as $each) {
                    /* 如果课程信息因为不符合格式而无法匹配 */
                    if (isset($each['raw'])) {
                        $replyText .= "\n".$each['raw']."\n";
                        #log
                    }
                    $beginTimeIndex = $each['from_section'] - 1;
                    /* 如果本周有这节课 */
                    if ($currWeek >= $each['from_week'] && $currWeek <= $each['to_week']) {
                        $replyText .= "-- {$beginTime[$beginTimeIndex]} --\n"
                            ."{$each['name']} ({$each['teacher']})\n"
                            ."{$each['from_week']}-{$each['to_week']}周, {$each['from_section']}-{$each['to_section']}节\n"
                            ."{$each['place']}\n\n";
                    } else {
                        continue;
                    }
                }
                /* 如果没有符合要求的课程 */
                if ($replyText == "") {
                    $replyText = "你今天没有课哦，可以去轻松一下啦 :) \n";
                }
            } else {
                $replyText = "你今天没有课哦，可以去轻松一下啦 :) \n";
            }


            /* 处理调课信息 */
            $adjInfo = $timetable['data']['adj_info'];

            if (isset($adjInfo)) {
                $adjInfoString = "";
                foreach ($adjInfo as $each) {
                    if (isset($each['origin']['raw']) ||
                        isset($each['modified']['raw'])
                    ) {
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
                        $adjInfoString .= "课程：{$className}\n由第".$fromWeekStr."周,"
                            .SchoolDatetime::weekTranslate(intval($each['origin']['day_in_week']))
                            ."第{$each['origin']['from_section']}节调至\n第"
                            .$toWeekStr."周,"
                            .SchoolDatetime::weekTranslate(intval($each['origin']['day_in_week']))
                            ."第{$each['modified']['to_section']}节\n\n";
                    }
                }
            }

            if ($adjInfoString != "") {
                $adjInfoString = "--- 调课信息 ---\n".$adjInfoString;
            }
            $extraInfo = "点击查看课程详情";
            $items = [
                new NewsItem(
                    [
                        'title'       => '课程安排',
                        'description' => $replyText.$adjInfoString.$extraInfo,
                        'url'       => 'http://ssfw.scuec.edu.cn/ssfw/login.jsp',
                    ]
                )
            ];
            return $items;
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

    private function parseTimetable($arrTimetable)
    {
        $beginTime = array("08:00", "08:55", "10:00", "10:55", "14:10", "15:05", "16:00", "16:55", "18:40", "19:30", "20:20");
        $tiyuTime = array("08:00", "08:55", "10:00", "10:00", "11:30", "14:10", "16:00", "16:10", "17:40", "19:30", "20:20");
        $result = array();
        foreach ($arrTimetable as $day => $courses) {
            $result[$day] = array();
            foreach ($courses as $courseCount => $eachCourseInfoArr) {
                $string = $eachCourseInfoArr[1];
                $beginSection = $eachCourseInfoArr[0]; //备用值
                if (strlen($string) >= 6) {
                    $string=preg_replace("/\(\d.+\n.+\d{1,2}\)\s/s", "", $string);
                    /* parse class information */
                    if (preg_match("/(.*)\s(\d{1,2})-(\d{1,2})周,(\d{1,2})-(\d{1,2})周,(\d{1,2})-(\d{1,2})周.+第(\d{1,2})-(\d{1,2})节.+>(.*)<br>(.+)$/U", $string, $parseResult)) {
                        $final = array(
                            'name' => $parseResult[1],
                            'from_week' => $parseResult[2].",".$parseResult[4].",".$parseResult[6],
                            'to_week' => $parseResult[3].",".$parseResult[5].",".$parseResult[7],
                            'from_section' => $parseResult[8],
                            'to_section' => $parseResult[9],
                            'teacher' => $parseResult[10],
                            'place' => $parseResult[11],
                        );
                        if (strpos($final['name'], '体育') !== false) {
                            $final['special_time']=array($tiyuTime[$final['from_section']],$tiyuTime[$final['to_section']]);
                        }
                        if (strpos($string, '周(双)') !== false) {
                            $final['special_week'] = 2;
                        } elseif (strpos($string, '周(单)') !== false) {
                            $final['special_week'] = 1;
                        }
                    } elseif (preg_match("/(.*)\s(\d{1,2})-(\d{1,2})周.+第(\d{1,2})-(\d{1,2})节.+>(.*)<br>([^[[\]]*?)$/U", $string, $parseResult)) {
                        $final = array(
                            'name' => $parseResult[1],
                            'from_week' => $parseResult[2],
                            'to_week' => $parseResult[3],
                            'from_section' => $parseResult[4],
                            'to_section' => $parseResult[5],
                            'teacher' => $parseResult[6],
                            'place' => $parseResult[7],
                        );
                        if (strpos($final['name'], '体育') !== false) {
                            $final['special_time']=array($tiyuTime[$final['from_section']],$tiyuTime[$final['to_section']]);
                        }
                        if (strpos($string, '周(双)') !== false) {
                            $final['special_week'] = 2;
                        } elseif (strpos($string, '周(单)') !== false) {
                            $final['special_week'] = 1;
                        }
                    } else {
                        $string = strip_tags($string);
                        $final = array(
                            'from_section' => $beginSection,
                            'raw' => $string
                        );
                    }
                    $result[$day][] = $final;
                } else {
                    continue;
                }
            }
        }
        return $result;
    }

    private function parseAdjustmentInfo(array $adj)
    {
        $currAdjInfo = array();
        foreach ($adj[1] as $key => $origin) {
            /*
             * $adj[1] 原课程数组
             * $adj[2] 调课结果数组
             */
            $modified = $adj[2][$key];

            /* 解析原课程 */
            $ori = explode(';', HelperService::removeHtmlEntities($origin));
            $matchCount = preg_match("/第(\d+)-(\d+)周\s(.+)\s(\d+)-(\d+)节/", $ori[0], $match);
            if ($matchCount !== 0) {
                $originArr = array(
                    'from_week' => intval($match[1]),
                    'to_week' => intval($match[2]),
                    'day_in_week' => SchoolDatetime::weekTranslate($match[3]),
                    'from_section' => intval($match[4]),
                    'to_section' => intval($match[5]),
                    'teacher' => $ori[1],
                    'place' => $ori[2]
                );
            } else {
                $originArr = array('raw' => HelperService::removeHtmlEntities($origin));
            }

            /* 解析调课课程 */
            $mod = explode(';', HelperService::removeHtmlEntities($modified));
            $matchCount = preg_match("/第(\d+)-(\d+)周\s(.+)\s(\d+)-(\d+)节/", $mod[0], $match);
            if ($matchCount !== 0) {
                $modifiedArr = array(
                    'from_week' => intval($match[1]),
                    'to_week' => intval($match[2]),
                    'day_in_week' => SchoolDatetime::weekTranslate($match[3]),
                    'from_section' => intval($match[4]),
                    'to_section' => intval($match[5]),
                    'teacher' => $mod[1],
                    'place' => $mod[2]
                );
            } else {
                $modifiedArr = array('raw' => HelperService::removeHtmlEntities($modified));
            }

            $currAdjInfo[]= array(
                'origin' => $originArr,
                'modified' => $modifiedArr
            );
        }
        return $currAdjInfo;
    }
}
