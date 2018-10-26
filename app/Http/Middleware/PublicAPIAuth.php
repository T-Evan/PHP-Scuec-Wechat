<?php

namespace App\Http\Middleware;

use Closure;
use Dingo\Api\Routing\Helpers;
use Validator;

class PublicAPIAuth
{
    use Helpers;

    const STATUS_INVALID_SIGN = 40300;
    const MSG_INVALID_SIGN = 'you are limited to fetch resource';

    protected $keys = [
        [
            'appid' => 'ZTk5MTA5MmY3ZjQ1',
            'token' => 'NmVmOWJkYjhhODI5YmJiMDUxZWRjZTcy'
        ]
    ];

    /**
     * 验证规则:
     *  appid
     *  ts      时间戳
     *  sign = sha1({appid}{ts}{token})
     *  签名(sign)为appid，ts，token按顺序连接后sha1
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $validations = [
            'appid' => 'required',
            'ts'    => 'required',
            'sign'  => 'required'
        ];
        $validator = Validator::make($request->input(), $validations);
        if ($validator->fails()) {
            $this->response->errorForbidden();
        }
        $appid  = $request->get('appid');
        $ts     = $request->get('ts');
        $sign   = $request->get('sign');
        $token = $this->verifyAPPID($appid);
        if (!$token) {
            $this->response->errorForbidden();
        }
        $realSign = sha1($appid.$ts.$token);
        if ($realSign != $sign) {
            $this->response->errorForbidden();
        }
        return $next($request);
    }

    protected function verifyAPPID(string $appid)
    {
        foreach ($this->keys as $key) {
            if ($key['appid'] == $appid) {
                return $key['token'];
            }
        }
        return false;
    }
}
