<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 10/12/18
 * Time: 19:10
 */

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Api\PhysicalExperiment\LabInfoSpider;
use App\Http\Service\AccountService\Facades\Account;
use App\Http\Service\HelperService;
use App\Http\Service\WechatService\Exceptions\NetworkException;
use App\Http\Service\WechatService\Exceptions\WechatAuthException;
use App\Http\Service\WechatService\Facades\WechatService;
use App\Http\Service\WechatService\OAuth;
use App\Models\StudentInfo;
use Illuminate\Http\Request;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class PhysicalExperimentController extends Controller
{
    private $BIND_URL;

    protected static $STATE_SALT    = 'lab_info_query';
    protected static $REDIS_CONN     = 'phy_exp';

    const STATUS_SUCCESS                = 200;
    const STATUS_OPENID_RETRIEVED_FAIL  = 413;
    const STATUS_OPERATION_TOO_FAST     = 423;
    const STATUS_STATE_INVALID          = 433;

    const STATUS_CONN_TIMEOUT           = 404;
    const STATUS_CONN_FORBIDDEN         = 403;

    const TTL_QUERY_STATE               = 300;
    // 尝试次数限定
    const TRY_COUNT_LIMIT               = 8;
    // 最短操作时间限定
    const MIN_OPERATION_GAP             = 2;

    public function __construct()
    {
        $openId = Account::getOpenid();
        $this->BIND_URL = WechatService::oauth()
            ->setScope(OAuth::SCOPE_BASE)
            ->setCallbackUrl(route('phy_exp.bind'))
            ->setState(sha1($openId.self::$STATE_SALT))
            ->getRedirectUrl();
    }

    public function handle()
    {
        $account = Account::getAccount();
        if (!$account) {
            return '你还没有绑定教务系统账户，请先绑定吧.'.HelperService::getBindingLink('ssfw');
        }
        if (!Account::isBindLib()) {
            return "先去<a href=\"{$this->BIND_URL}\">绑定大物实验的账户吧</a>";
        }
        $labInfoSpider = new LabInfoSpider($account->id, $account->lab_password);
//        $labInfoSpider
    }

    public function bindAccountView(Request $request)
    {
        return view('static_pages.phy_exp');
    }

    public function verifyState(Request $request)
    {
        $validation = [
            'code' => 'required',
            'state' => 'required|string'
        ];
        $validator = Validator::make($request->input(), $validation);
        if ($validator->fails()) {
            return [
                'status' => self::STATUS_STATE_INVALID,
                'msg' => 'param missing'
            ];
        }
        $code = $request->get('token');
        $state = $request->get('state');
        try {
            $userInfo = WechatService::userinfo($code)->echAccessToken();
        } catch (NetworkException $e) {
            return [
                'status' => self::STATUS_OPENID_RETRIEVED_FAIL,
                'msg' => 'network error#1'
            ];
        } catch (WechatAuthException $e) {
            return [
                'status' => self::STATUS_OPENID_RETRIEVED_FAIL,
                'msg' => 'invalid code'
            ];
        }
        $openid = $userInfo['openid'];
        if ($openid != sha1(Account::getOpenid().self::$STATE_SALT)) {
            return ['status' => self::STATUS_STATE_INVALID];
        }
        $redisKey = $this->getRedisKey("$state");
        $conn = $this->getRedisConn();
        $conn->hmset($redisKey, [
            'openid' => $openid,
            'count' => 0,
            'stamp' => time()
        ]);
        $conn->expire($redisKey, self::TTL_QUERY_STATE);
        return $this->jsonResponse(self::STATUS_SUCCESS);
    }

    public function labAccount(Request $request)
    {

    }

    public function bindAccount(Request $request)
    {
        $state = $request->get('state');
        $data = $request->get('data');
        $userid = $data['userid'];
        $passwd = $data['token'];
        $redisKey = $this->getRedisKey($state);
        $conn = $this->getRedisConn();
        $bindStatus = $conn->hgetall($redisKey);
        if (!$bindStatus) {
            // timeout
            return $this->jsonResponse(self::STATUS_STATE_INVALID);
        }
        $conn->hset($redisKey, 'stamp', (string)time());
        $conn->hIncrby($redisKey, 'count', 1);
        $count = $bindStatus['count'];
        if ($count > self::TRY_COUNT_LIMIT) {
            return $this->jsonResponse(
                self::STATUS_STATE_INVALID,
                'too much requests in limit time'
            );
        }
        $timestamp = $bindStatus['stamp'];
        $openid = $bindStatus['openid'];
        if (time() - $timestamp < self::MIN_OPERATION_GAP) {
            return $this->jsonResponse(
                self::STATUS_OPERATION_TOO_FAST,
                'operation too fast',
                ['interval' => time()/$timestamp]
            );
        }
        $labInfoSpider = new LabInfoSpider($userid, $passwd);
        $cookie = $labInfoSpider->getCookie();
        $accountStatus = $cookie['status'];
        if ($accountStatus != 200) {
            // 获取cookie失败
            return $this->responseAccountError($accountStatus);
        }
        $studentAccout = StudentInfo::where('openid', $openid)->first();
        $studentAccout->lab_password = $passwd;
        if (!$studentAccout->save()) {
            Log::error("server error: update student-info sql operation failed."
                ."(openid: $openid, password: $passwd)");
        }
        return $this->jsonResponse(self::STATUS_SUCCESS);
    }

    private function responseAccountError(int $status): array
    {
        switch ($status) {
            case 404:
                return $this->jsonResponse(self::STATUS_CONN_TIMEOUT, 'connection timeout');
                break;
            case 403:
                return $this->jsonResponse(self::STATUS_CONN_FORBIDDEN, 'password or account is invalid');
                break;
        }
    }

    private function getRedisConn(): Connection
    {
        return Redis::connection(self::$REDIS_CONN);
    }

    private function getRedisKey(string $state)
    {
        return "lab_verify:state:$state";
    }

    protected function jsonResponse(int $status, string $msg = '', array $data = [])
    {
        if ($data) {
            return [
                'status' => $status,
                'msg' => $msg,
                'data' => $data
            ];
        }
        return [
            'status' => $status,
            'msg' => $msg
        ];
    }
}
