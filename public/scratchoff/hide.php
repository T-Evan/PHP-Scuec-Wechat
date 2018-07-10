<?php
/**
 * This api is used for hiding the grades-item that user doesn't like
 * For the unity of tech-stack, here I use Redis to persist user states
 * 
 * Data structure
 * (Hash, ttl:30d) user:{$openId}:score:hidden
 *          - className.score => 1 // hidden
 *          - className.score => 0 // show
 *
 * METHOD: PUT
 * Parameter(s):
 *  {
 *      course_name: ''
 *      course_score: ''
 *      openid: ''
 *      discard: '' [optional: when discard hidden]
 *  }
 * @author itsl<itsl@foxmail.com>
 * @date 2018-5-29
 */

require_once __DIR__."/../vendor/autoload.php";
require_once __DIR__."/../class/conn.php";
require_once __DIR__."/../class/RedisConfig.php";
require_once __DIR__."/scratchoff.php";

use \Zixunminda\Scratchoff\Response;

// change mime-type to json
header("Content-Type: application/json; charset=utf-8");
// and access allow
header("Access-Control-Allow-Origin: *");

$redis = new Zixunminda\Redis();
$redis->select(1);

// check the request method
if (strtoupper($_SERVER['REQUEST_METHOD']) != 'PUT') {
    echo Response::fail('Method not allowed.');
    die();
}

$param = json_decode(file_get_contents("php://input"), true);

// check parameter(s) error
if (!$param
    || !isset($param['openid'])
    || !isset($param['course_name'])
    || !isset($param['course_score'])) {
    echo Response::fail('Error', Response::STATUS_FAIL_PARAM_INVALID);
    die();
}

$openid = $param['openid'];
$courseName = $param['course_name'];
$courseScore = $param['course_score'];

// check if the user's course cache exists
$courseList = $redis->get("user:{$openid}:score:cache");
if ($courseList == false) {
    echo Response::fail('user\'s course cache not exist',
        Response::STATUS_FAIL_PARAM_INVALID);
    die();
}

// check if this course exists
$courseList = json_decode(base64_decode($courseList), true);
if (!isset($courseList['info']) || !$courseList['info']) {
    echo Response::fail('data error');
    die();
}
$courseExist = false;
foreach ($courseList['info'] as $key => $val) {
    if ($courseList['info'][$key]['name'] == $courseName
        && $courseList['info'][$key]['score'] == $courseScore) {
        $courseExist = true;
        break;
    }
}
if (!$courseExist) {
    echo Response::fail('course not exist');
    die();
}

// add the hidden flag to redis-db
if (isset($param['discard'])) $hidden = '0';
else $hidden = '1';
$redis->hSet("user:{$openid}:score:hidden", $courseName.$courseScore, $hidden);
$redis->setTimeout("user:{$openid}:score:hidden", ScratchOff::HIDDEN_TIMEOUT);
unset($redis);

echo Response::success();
die();
