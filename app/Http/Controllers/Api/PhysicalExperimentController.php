<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 10/12/18
 * Time: 19:10
 */

namespace App\Http\Controllers\Api;


use App\Http\Service\AccountService\Facades\Account;
use App\Http\Service\WechatService\Exceptions\NetworkException;
use App\Http\Service\WechatService\Exceptions\WechatAuthException;
use App\Http\Service\WechatService\Facades\WechatService;
use App\Http\Service\WechatService\OAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class PhysicalExperimentController
{
    private $BIND_URL;

    protected static $STATE_SALT    = 'lab_info_query';
    protected static $REDIS_KEY     = 'phy_exp';

    const STATUS_SUCCESS                = 200;
    const STATUS_OPENID_RETRIEVED_FAIL  = 413;
    const STATUS_STATE_INVALID          = 433;

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
        if (!Account::isBindLib()) {
            return "先去<a href=\"{$this->BIND_URL}\">绑定大物实验的账户吧</a>";
        }
    }

    public function bindAccountView(Request $request)
    {
        return view('static_pages.phy_exp');
    }

    public function getStatus(Request $request)
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
        Redis::connection(self::$REDIS_KEY)->hmset("lab_verify:state:".$state, [
            'openid' => $openid,
            'count' => 0,
            'stamp' => time()
        ]);
        return ['status' => self::STATUS_SUCCESS];
    }

}
