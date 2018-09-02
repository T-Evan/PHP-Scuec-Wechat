<?php
/**
 * Datetime
 * create on 20160318
 * @author er1cst
 * @version 0.1
 */
namespace App\Http\Service;

use App\Models\Common;

class SchoolDatetime
{
    /**
     * get the week number counting from the beginning
     * 获取周数
     * @return int the current week
     */
    public static function getSchoolWeek()
    {
        $start = strtotime(config('app.semester_begin_date'));
        $end = time();
        return self::weekDiff($start, $end);
    }

    /**
     * get the day number counting from the beginning
     * 获取今天是本学期第几天
     * @return int
     */
    public static function getSchoolDay()
    {
        $semesterStartTimestamp = strtotime(config('app.semester_begin_date'));
        $now = time();
        $diff = ceil(($now - $semesterStartTimestamp) / 86400);
        return $diff;
    }

    /**
     * get the total week count of a semester
     * 获取本学期总周数
     * @return int
     */
    public static function getWeekCount()
    {
        $beginTimestamp = strtotime(config('app.semester_begin_date'));
        $endTimestamp = strtotime(config('app.semester_end_date'));
        return self::weekDiff($beginTimestamp, $endTimestamp);
    }

    /**
     * get the total day count
     * 获取本学期总天数
     * @return int
     */
    public static function getDayCount()
    {
        $beginTimestamp = strtotime(config('app.semester_begin_date'));
        $endTimestamp = strtotime(config('app.semester_end_date'));
        $diff = ceil(($endTimestamp - $beginTimestamp) / 86400);
        return $diff;
    }

    /**
     * 中文星期/索引号互译. 索引号以date('N');为准
     * @param int/string
     * @return string/int/false
     */
    public static function weekTranslate($arg)
    {
        if (is_int($arg)) {
            $map = array(
                1 => "星期一",
                2 => "星期二",
                3 => "星期三",
                4 => "星期四",
                5 => "星期五",
                6 => "星期六",
                7 => "星期日"
            );
        } else {
            $map = array(
                "星期一" => 1,
                "星期二" => 2,
                "星期三" => 3,
                "星期四" => 4,
                "星期五" => 5,
                "星期六" => 6,
                "星期日" => 7
            );
        }
        return HelperService::element($arg, $map, false);
    }

    /**
     * @return string
     */
    public static function getDateInfo()
    {
        $date = date("Y年m月d日");
        $weekIndex = intval(date('N'));
        $date .= " ".self::weekTranslate($weekIndex);
        $currentDay = self::getSchoolDay();
        $currentWeek = self::getSchoolWeek();
        $weekCount = sprintf("本学期 第%02d周,第%02d天", $currentWeek, $currentDay);

        /*
         * PROGRESS BAR!
         * [||||||'   ] 65%
         * [||||||||||] 100%
         */
        //$rate = $currentDay / self::getDayCount();
        //$progressBar = ProgressBar::genProgressBar($rate, 14);
        //return $date."\n".$progressBar."\n".$weekCount."\n";
        return $date."\n".$weekCount."\n";
    }

    private static function weekDiff($startTimestamp, $endTimestamp)
    {
        $startDayOfWeek = (int)date('N', $startTimestamp);
        $days = floor(($endTimestamp - $startTimestamp) / 86400);
        $weeks = floor(($days + ($startDayOfWeek - 1)) / 7) + 1;
        // 如果还没有开学，则返回周数为0，代表没有开学
        return $weeks > 0 ? $weeks : 0;
    }
}
