<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SchoolInfoException;
use App\Http\Controllers\StudentsController;
use App\Http\Service\HelperService;
use App\Http\Service\SchoolDatetime;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class AccountInfoDetailController extends Controller
{
    public function getTimeTable($year = null, $term = null, $debug = false)
    {
        $message = app('wechat')->server->getMessage();
        $openid = $message['FromUserName'];
        $redis= Redis::connection('timetable');
        $timetable_cache = $redis->get('timetable_'.$openid);
        if (!empty($timetable_cache)) {
            $pass_time =config('app.timetable_cache_time')-$redis->ttl('timetable_'.$openid);//已缓存了多久
            $timetable = array(
                'status' => 200,
                'message' => __CLASS__ . ': get timetable successfully',
                'data' => json_decode($timetable_cache, true),
                'update_time' => Carbon::now()->subSeconds($pass_time)->diffForHumans()
            );
            return $timetable;
        }
        $student_controller = new StudentsController();
        $cookie_array = $student_controller->cookie('ssfw');
        if ($cookie_array['data'] == null) {
            return $cookie_array['message'];
        }
        $cookie = unserialize($cookie_array['data']);
        $res = HelperService::get(
            'http://id.scuec.edu.cn/authserver/login?goto=http%3A%2F%2Fssfw.scuec.edu.cn%2Fssfw%2Fj_spring_ids_security_check',
            $cookie,
            'http://ssfw.scuec.edu.cn/ssfw/index.do'
        );
        $jar1 = $res['cookie'];
        $res = HelperService::get(
            config('app.timetable_url'),
            $jar1,
            'http://ssfw.scuec.edu.cn/ssfw/index.do'
        );
        $table_html = $res['res']->getbody()->getcontents();

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
                ),
                'update_time' => '刚刚'

            );
            //添加未安排课程信息
            if (isset($no_arrange)) {
                $rtnArray['data']['no_arrange']=$no_arrange;
            }

            $redis->setex(
                'timetable_'.$openid,
                config('app.timetable_cache_time'),
                json_encode($rtnArray['data'])
            ); //缓存课表两小时
            if ($debug == true) {
                $rtnArray['raw_timetable'] = $trimedTable;
            }
            return $rtnArray;
        }
    }

    public function getScoreInfo($year = false, $term = false)
    {
        $message = app('wechat')->server->getMessage();
        $openid = $message['FromUserName'];
//        $openid = 'onzftwySIXNVZolvsw_hUvvT8UN0';
        $redis= Redis::connection('score');
        $score_cache = $redis->get('score_'.$openid);
        if (!empty($score_cache)) {
            $pass_time =config('app.score_cache_time')-$redis->ttl('score_'.$openid);//已缓存了多久
            $score = array(
            'status' => 200,
            'message' => __CLASS__ . ': get score successfully',
            'info' => json_decode($score_cache, true),
            'update_time' => Carbon::now()->subSeconds($pass_time)->diffForHumans()
             );
            return $score;
        }
        $student_controller = new StudentsController();
        $cookie_array = $student_controller->cookie('ssfw');
        if ($cookie_array['data'] == null) {
            return $cookie_array['message'];
        }
        /**
         * goto后面的值Base64解码后为http://ssfw.scuec.edu.cn/ssfw/j_spring_ids_security_check
         * 表示认证用户信息后跳转的地址
         */
        $cookie = unserialize($cookie_array['data']);
        $res = HelperService::get(
            'http://id.scuec.edu.cn/authserver/login?goto=http%3A%2F%2Fssfw.scuec.edu.cn%2Fssfw%2Fj_spring_ids_security_check',
            $cookie,
            'http://ssfw.scuec.edu.cn/ssfw/index.do'
        );
        $jar1 = $res['cookie'];

        //尝试获取本科生成绩信息
        if (($year !== false) && ($term !== false)) {
            $url = "http://ssfw.scuec.edu.cn/ssfw/zhcx/cjxx?qXndm_ys={$year}&qXqdm_ys={$term}";
        } else {
            $url = "http://ssfw.scuec.edu.cn/ssfw/zhcx/cjxx";
        }
        $res = HelperService::get(
            $url,
            $jar1,
            'http://ssfw.scuec.edu.cn/ssfw/index.do'
        );
        $score_html = $res['res']->getbody()->getcontents();

        if (strpos($score_html, "评教") !== false) {  //判断是否评教
            return array(
                'status' => 205,
                'message' => __CLASS__ . ": 未评教",
                'info' => '',
                'update_time' => '刚刚'
            );
        } elseif (strpos($score_html, "原始成绩")) {
            preg_match("/<table class=\"table_con\" base=\"color3\"[\s\S]+复查操作[\s\S]+?<\/table>/", $score_html, $matches);
            unset($score_html);   // 释放占用的内存
            if (strpos($matches[0], "暂无记录") !== false) {
                if ($openid !== 'null') {
                    $redis->hSet("user:public:score:score_amount", $openid, 0);
                }
                return array(
                    'status' => 204,
                    'message' => __CLASS__ . ": 暂无成绩",
                    'info' => '',
                    'update_time' => '刚刚'
                );
            } else {
                preg_match_all("/<tr class=\"t_con\">([\s\S]*?)<\/tr>/", $matches[0], $matches);
                for ($i=0; $i < count($matches[1]); $i++) {
                    preg_match_all("/<td align=\"center\" valign=\"middle\">([\s\S]*?)<\/td>/", $matches[1][$i], $td_matches);
                    /*$td_matches
                          0 => "1"
                          1 => "2017-2018学年第二学期"
                          2 => "115100000113"
                          3 => "就业指导"
                          4 => "公共必修课&nbsp;"
                          5 => "必修课&nbsp;"
                          6 => "1.0&nbsp;"
                          7 => """
                            \r\n
                            \t                  \t  \t  \r\n
                            \t                  \t  \t  \t\r\n
                            \t                  \t  \t  \t\r\n
                            \t                  \t  \t  \t\t<span><strong>73&nbsp;</strong></span>\r\n
                            \t                  \t  \t  \t\r\n
                            \t                  \t  \t  \r\n
                            \t                  \t
                            """
                          8 => "初修&nbsp;"
                          9 => "92/107&nbsp;"
                          10 => "<strong></strong><br/>"
                     */
                    preg_match('/\d{1,3}/', $td_matches[1][7], $score);
                    $course[$i]['name'] = trim($td_matches[1][3]); // 课程名称
                    $course[$i]['type'] = str_replace('&nbsp;', '', $td_matches[1][4]); // 课程类别
                    $course[$i]['credits'] = str_replace('&nbsp;', '', $td_matches[1][6]);   // 班级排名
                    $course[$i]['score'] = $score[0];   // 课程成绩
                    $course[$i]['rank'] = str_replace('&nbsp;', '', $td_matches[1][9]);   // 班级排名
                }
                $this->isNewScoreExists($openid, $redis, count($course));
            }
        }
        $redis->setex('score_'.$openid, config('app.score_cache_time'), json_encode($course)); //缓存考试两小时
        return array(
            'status' => 200,
            'message' => __CLASS__ . ": get the test arrangement successfully",
            'info' => $course,
            'update_time' => '刚刚'
        );
    }

    public function getExamArrangement()
    {
        $message = app('wechat')->server->getMessage();
        $openid = $message['FromUserName'];
        $redis= Redis::connection('exam');
        $exam_cache = $redis->get('exam_'.$openid);
        if (!empty($exam_cache)) {
            $pass_time =config('app.exam_cache_time')-$redis->ttl('exam_'.$openid);//已缓存了多久
            $exam = array(
                'status' => 200,
                'message' => __CLASS__ . ': get exam successfully',
                'data' => json_decode($exam_cache, true),
                'update_time' => Carbon::now()->subSeconds($pass_time)->diffForHumans()
            );
            return $exam;
        }
        $student_controller = new StudentsController();
        $cookie_array = $student_controller->cookie('ssfw');
        if ($cookie_array['data'] == null) {
            return $cookie_array['message'];
        }
        $cookie = unserialize($cookie_array['data']);
        /**
         * goto后面的值Base64解码后为http://ssfw.scuec.edu.cn/ssfw/j_spring_ids_security_check
         * 表示认证用户信息后跳转的地址
         */
        $res = HelperService::get(
            'http://id.scuec.edu.cn/authserver/login?goto=http%3A%2F%2Fssfw.scuec.edu.cn%2Fssfw%2Fj_spring_ids_security_check',
            $cookie,
            'http://ssfw.scuec.edu.cn/ssfw/index.do'
        );
        $jar1 = $res['cookie'];
        $res = HelperService::get(
            'http://ssfw.scuec.edu.cn/ssfw/xsks/kcxx',
            $jar1,
            'http://ssfw.scuec.edu.cn/ssfw/index.do'
        );
        $exam_html = $res['res']->getbody()->getcontents();

        if (!empty($exam_html)) {
            if (strpos($exam_html, "已安排考试课程") !== false) {
                preg_match("/<table class=\"table_con\"[\s\S]+?座位号[\s\S]+?<\/table>/", $exam_html, $matches);
                preg_match_all("/<tr class=\"t_con\">[\s\S]+?<\/tr>/", $matches[0], $arrTr);
                $testAmount = count($arrTr[0]);
                $arrTestInfo = array();
                foreach ($arrTr[0] as $key => $value) {
                    preg_match_all("/<td.*>(.+?)<\/.*td>/", $value, $matches);
                    /* trim all strings in the array*/
                    $countofMatches = count($matches[1]);
                    for ($i=0; $i<$countofMatches; $i++) {
                        trim($matches[1][$i]);
                    }
                    $arrTestInfo[] = $matches[1];
                }
                $redis->setex('exam_'.$openid, config('app.exam_cache_time'), json_encode($arrTestInfo)); //缓存考试两小时
                return array(
                    'status' => 200,
                    'message' => __CLASS__ . ": get the test arrangement successfully",
                    'data' => $arrTestInfo,
                    'update_time' => '刚刚'
                );
            /* WHY CAN'T IT WORK?
             * It seems that an encoding problem occured. ALL Chinese characters
             * cannot be decoded correctly while the html parsing is successful.
             * Besides, WHY CAN'T I USE CHINESE INPUT METHOD IN SUBLIME for LINUX?

            $htmlDom = new \HtmlParser\ParserDom($matches[0]);
            $arrTr = $htmlDom->find('tr');
            $courseCount = count($arrTr)-1;    // 课程数量
            for ($i=0; $i < $courseCount; $i++) {
                $td = $arrTr[$i+1]->find('td');
                $course[$i]['name'] = trim($td[2]->getPlainText());    // 课程名称
                $course[$i]['teacher'] = trim($td[4]->getPlainText());    // 教师
                $course[$i]['seat'] = trim($td[6]->getPlainText());    // 座次
                $course[$i]['time'] = trim($td[7]->getPlainText());    // 时间
                $course[$i]['class'] = trim($td[8]->getPlainText());    // 考场位置
                // 考试状态
                if (trim($td[11]->getPlainText()) == "已结束") {
                    $course[$i]['status'] = 'end';
                }
                else{
                    $course[$i]['status'] = 'ing';
                }
            }
            */
            } else {
                return array(
                    'status' => 204,
                    'message' => "没有考试信息",
                    'data' => null
                );
            }
        } else {
            return array(
                'status' => 404,
                'message' => "超时",
                'data' => null
            );
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
    private function isNewScoreExists($openid, $redis, $newAmount)
    {
        $oldAmount = $redis->hGet("user:public:score:score_amount", $openid);
        if ($oldAmount === false) {
            $redis->hSet("user:public:score:score_amount", $openid, $newAmount);
            $newScoreCount = 0;
        } else {
            $newScoreCount = $newAmount - $oldAmount;
            $newScoreCount = ($newScoreCount < 0) ? 0 : $newScoreCount;
            if ($newScoreCount >= 0) {
                $redis->hSet("user:public:score:score_amount", $openid, $newAmount);
            }
        }
        return $newScoreCount;
    }
}
