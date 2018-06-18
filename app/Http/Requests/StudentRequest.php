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
            'account' => 'required|unique:students,account',
            'password' => 'required|',
        ];
    }

    public function messages()
    {
        return [
            'account.required' => '还没有输入账号呢',
            'password.required' => '还没有输入密码呢',
        ];
    }

}
