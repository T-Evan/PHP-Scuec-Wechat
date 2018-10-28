<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\AccountInfoDetailController;
use App\Http\Controllers\Api\AccountManager\AccountManagerFactory;
use App\Http\Requests\StudentRequest;
use App\Models\StudentInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class StudentsController extends Controller
{
    public function create(Request $request)
    {
        $array = [
            'type' => $request->type,
            'openid' => $request->openid,
        ];

        return view('static_pages.login', $array);
    }

    public function store(StudentRequest $studentRequest, Bool $refreshCookie = false)
    {
        $account = $studentRequest->account;
        $password = $studentRequest->password;
        $type = $studentRequest->type;
        $openid = $studentRequest->openid;
        //学校认证平台post字段为username，因此需要将account重新构造数组
        $user_info_array = [
            'username' => $account,
            'password' => $password,
        ];
        switch ($type) {
            case 'ssfw':
                $result = $this->api->post('students/ssfw', $user_info_array); //dingo内部调用
                break;
            case 'lib':
                $result = $this->api->post('students/lib', $user_info_array); //dingo内部调用
                break;
            case 'lab':
                $result = $this->api->post('students/lab', $user_info_array);
                break;
            default:
                $result = ['message' => '错误的提交'];
                break;
        }

        $accountManager = AccountManagerFactory::getAccountManager($type);
        $validationResult = $accountManager->validateAccount();

        if ($validationResult->isFailed()) {
            session()->flash('danger', '错误：'.$result['message']);
            return view('static_pages.login', [
                'type' => $type,
                'openid' => $openid,
                //主要用做刷新缓存函数调用此函数时的判断逻辑
                'message' => $validationResult->getMsg(),
            ]);
        }

        Redis::connection('default')
            ->setex($type.'_'.$openid, 3600, $result['data']['cookie']);
        $student = StudentInfo::where('openid', $openid);
        if (!$refreshCookie) {   //重新绑定时需要刷新缓存并更新密码
            if (empty($student->get()->first())) {
                $student = StudentInfo::create([
                    'openid' => $openid,
                    'account' => $account,
                    $type.'_password' => encrypt($password), //拼接要保存的密码类型
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
                $redis->del('exam_'.$openid);

                session()->flash('info', '提醒：账号绑定信息更新成功！缓存数据已刷新。点击左上角返回聊天窗口，再次回复关键字即可。');
            }
        }

        return view('static_pages.login', [
            'type' => $type,
            'openid' => $openid,
            'message' => $result['message'], //主要用做刷新缓存函数调用此函数时的判断逻辑
        ]);
    }

    public function test()
    {
        $student = new AccountInfoDetailController();
        $student->getMoney();
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

        $key = $type.'_'.$openid;
        $cookie = Redis::get($key);
        if (!$cookie) {
            $password = $type.'_password'; //拼接数据表的密码字段
            $student = StudentInfo::select('account', $password, 'openid')
                ->where('openid', $openid)
                ->first();
            if (!isset($student->account)) {
                return ['data' => null, 'message' => '用户不存在'];
            }
            $studentRequest = new StudentRequest();
            $studentRequest->account = $student->account;
            if (null == $student->toArray()[$password]) {
                return ['data' => null, 'message' => '用户信息有误'];
            }
            $studentRequest->password = decrypt($student->toArray()[$password]);
            $studentRequest->openid = $student->openid;
            $studentRequest->type = $type;
            $res = $this->store($studentRequest)->getData();
            if ('用户账号密码正确' == $res['message']) {
                $cookie = Redis::get($key);
            } else {
                return ['data' => null, 'message' => '用户信息有误'];
            }
        }

        return ['data' => $cookie, 'message' => '获取cookie成功'];
    }
}
