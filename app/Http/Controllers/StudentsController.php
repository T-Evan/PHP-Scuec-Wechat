<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRequest;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentsController extends Controller
{
    public function create()
    {
        return view('static_pages.login');
    }

    public function store(StudentRequest $studentRequest)
    {
        $app = app('wechat');
        $message = $app->server->getMessage();
        $openid = $message['FromUserName'];
        $student = Student::where('openid', $openid)
            ->update('account', $studentRequest->account)
            ->update('ssfw_password', $studentRequest->ssfw_password);
        session()->flash('success', '欢迎，您将在这里开启一段新的旅程~');
        return redirect()->route('users.show', [$student]);
    }
}
