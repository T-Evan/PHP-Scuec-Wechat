<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\AccountInfoController;
use App\Http\Requests\StudentRequest;
use App\Http\Service\TimeTableReplyService;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class StudentsController extends Controller
{
    public function create(Request $request)
    {
        $array = [
            'type' => $request->type,
            'openid' => $request->openid
        ];
        return view('static_pages.login', $array);
    }
    public function test()
    {
//        $test = new AccountInfoController();
//        $userInfoArray=['openid'=>'onzftwySIXNVZolvsw_hUvvT8UN0'];
//        $res = $test->getTimeTable('onzftwySIXNVZolvsw_hUvvT8UN0');
        $test =new TimeTableReplyService('onzftwySIXNVZolvsw_hUvvT8UN0');
         $res = $test->test();

        dd($res);
    }
    public function store(StudentRequest $studentRequest)
    {
        $account = $studentRequest->account;
        $password = $studentRequest->password;
        $type = $studentRequest->type;
        $openid = $studentRequest->openid;
        //学校认证平台post字段为username，因此需要将account重新构造数组
        $user_info_array = [
            'username' => $account,
            'password' => $password
        ];
        switch ($type) {
            case 'ssfw':
                $result = $this->api->post('students/ssfw', $user_info_array); //dingo内部调用
                break;
            case 'lib':
                $result = $this->api->post('students/lib', $user_info_array); //dingo内部调用
                break;
        }
        if (!empty($result['data'])) { //验证通过
            Redis::set($type.'_'.$openid, $result['data']['cookie']);
            $student = Student::where('openid', $openid);
            if (empty($student->get()->first())) {
                $student = Student::create([
                            'openid' => $openid,
                            'account' => $account,
                            $type . '_password' => bcrypt($password) //拼接要保存的密码类型
                        ]);
                $student->save();
                session()->flush();
                session()->flash('success', '完成：初步绑定成功！点击左上角返回聊天窗口，再次回复关键字即可。');
            } else {
                $student->update(['account' => $account,
                            $type . '_password' => bcrypt($password)]);
                session()->flush();
                session()->flash('info', '提醒：账号绑定信息更新成功！缓存数据已刷新。点击左上角返回聊天窗口，再次回复关键字即可。');
            }
        } else {
            session()->flash('danger', '错误：'.$result['message']);
        }

        $array = [
            'type' => $type,
            'openid' => $openid
        ];
        return view('static_pages.login', $array);
    }
}
