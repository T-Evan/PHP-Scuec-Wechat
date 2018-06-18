<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\AccountInfoController;
use App\Http\Requests\StudentRequest;
use App\Http\Service\AccountInfoService;
use App\Models\Student;
use Illuminate\Http\Request;
use Validator;

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

    public function store(StudentRequest $studentRequest)
    {
        $user_info_array = [
            'username' => $studentRequest->account,
            'password' => $studentRequest->password
        ];
        $test = $this->api->post('students/judge', $user_info_array); //dingo内部调用
        if (!empty($test['data'])) { //成功获取cookie即验证通过
            $student = Student::where('openid', $studentRequest->openid);
            if (empty($student->get()->first())) {
                $student = Student::create([
                    'openid' => $studentRequest->openid,
                    'account' => $studentRequest->account,
                    $studentRequest->type . '_password' => $studentRequest->password //拼接要保存的密码类型
                ]);
                $student->save();
                session()->flush();
                session()->flash('success', '完成：初步绑定成功！点击左上角返回聊天窗口，再次回复关键字即可。');
            } else {
                $student->update(['account' => $studentRequest->account,
                    $studentRequest->type . '_password' => $studentRequest->password]);
                session()->flush();
                session()->flash('info', '提醒：账号绑定信息更新成功！缓存数据已刷新。点击左上角返回聊天窗口，再次回复关键字即可。');
            }
        } else {
            session()->flash('danger', '错误：'.$test['message']);
        }

        $array = [
            'type' => $studentRequest->type,
            'openid' => $studentRequest->openid
        ];
        return view('static_pages.login', $array);
    }
}
