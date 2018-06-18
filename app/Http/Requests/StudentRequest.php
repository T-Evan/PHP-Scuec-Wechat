<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Dingo\Api\Exception\ResourceException;

class StudentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'account' => 'required',
            'password' => 'required',
            'openid' => [
                'required',
                'regex:/^onzftw[a-zA-Z0-9_-]{22}/'
            ],
            'type'  => 'required|string|in:ssfw,lib',

        ];
    }

    public function messages()
    {
        return [
            'account.required' => '还没有输入账号呢',
            'password.required' => '还没有输入密码呢',
            'type.required' => 'type参数错误,请将这个错误告诉我们~',
            'type.in:' => 'type参数错误,请将这个错误告诉我们~',
            'openid.regex' => 'openid参数错误,重新获取绑定链接试试吧',
        ];
    }

}
