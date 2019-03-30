<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\AccountInfoController;
use App\Http\Controllers\Api\AccountInfoDetailController;
use App\Http\Controllers\Api\AccountManager\AccountManagerFactory;
use App\Http\Controllers\Api\AccountManager\BaseAccount;
use App\Http\Controllers\Api\AccountManager\EduSys\EduSysAccount;
use App\Http\Requests\StudentRequest;
use App\Models\StudentInfo;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class StudentsController extends Controller
{
    public function create(Request $request)
    {
        $array = [
            'type' => $request->type,
            'openid' => $request->openid,
        ];

        if ($request->has('redirect_url')) {
            session()->flash('redirect_url', $request->get('redirect_url'));
        }

        return view('static_pages.login', $array);
    }

    public function store(StudentRequest $studentRequest, Bool $refreshCookie = false)
    {
        $account    = $studentRequest->get('account');
        $password   = $studentRequest->get('password');
        $type       = $studentRequest->get('type');
        $openid     = $studentRequest->get('openid');

        $loginViewWithMsg = function ($msg) use ($openid, $type) {
            return redirect(route('students.create', [
                'type' => $type,
                'openid' => $openid,
                'message' => $msg
            ]));
        };

        $accountManager = AccountManagerFactory::getAccountManager($type);
        if (!$accountManager) {
            return $loginViewWithMsg("错误的提交");
        }

        $eduSysAccount = new BaseAccount();
        $eduSysAccount->setAccount($account);
        $eduSysAccount->setPassword($password);
        $eduSysAccount->setOpenid($openid);
        try {
            $validationResult = $accountManager->validateAccount($eduSysAccount);
        } catch (GuzzleException $e) {
            Log::error("http_client error[{$e->getFile()}:{$e->getLine()}]: ".$e->getMessage());
            return $loginViewWithMsg("服务器错误，请稍后再试");
        }

        if ($validationResult->isFailed()) {
            session()->flash('danger', '错误：'.$validationResult->getMsg());
            return $loginViewWithMsg($validationResult->getMsg());
        }

        $student = StudentInfo::where('openid', $openid);
        if (!$refreshCookie) {
            //重新绑定时需要刷新缓存并更新密码
            if (empty($student->get()->first())) {
                $student = StudentInfo::create([
                    'openid' => $openid,
                    'account' => $account,
                    //拼接要保存的密码类型
                    $type.'_password' => encrypt($password),
                ]);
                $student->save();
                session()->flush(); //清空缓存
                session()->flash('success', '完成：初步绑定成功！点击左上角返回聊天窗口，再次回复关键字即可。');
            } else {
                $student->update(['account' => $account,
                    $type.'_password' => encrypt($password), ]);
                session()->flush();

                //重新绑定，刷新cookie，通过绑定增加一定的操作复杂度，防止用户不停刷新
                $redis = Redis::connection('exam');
                $redis->del(['exam_'.$openid]);

                session()->flash('info', '提醒：账号绑定信息更新成功！缓存数据已刷新。点击左上角返回聊天窗口，再次回复关键字即可。');
            }
        }

        return $loginViewWithMsg($validationResult->getMsg());
    }

    public function test()
    {
        $student = new AccountInfoController();
        dd($student->getTableMessage());
    }

    /**
     * @param $type:账号类型, $testOpenid:测试接口调用此方法时可传递openid
     * @param null $testOpenid
     *
     * @return array
     */
    public function cookie($type, $testOpenid = null)
    {
        $common = app('wechat_common');
        $openid = $common->openid;

        if ($testOpenid) {
            $openid = $testOpenid;
        }

        $accountManager = AccountManagerFactory::getAccountManager($type);
        try {
            $cookieJar = $accountManager->getCookie($openid);
            $cookieString = serialize($cookieJar);
            return ['data' => $cookieString, 'message' => '获取cookie成功'];
        } catch (Api\AccountManager\Exceptions\AccountNotBoundException $e) {
            return ['data' => null, 'message' => '用户不存在'];
        } catch (Api\AccountManager\Exceptions\AccountValidationFailedException $e) {
            return ['data' => null, 'message' => '用户信息有误'];
        } catch (GuzzleException $e) {
            return ['data' => null, 'message' => '服务请求错误'];
        }
    }
}
