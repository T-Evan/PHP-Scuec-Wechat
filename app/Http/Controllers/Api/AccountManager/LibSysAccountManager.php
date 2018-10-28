<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 2018/10/27
 * Time: 11:59 AM
 */

namespace App\Http\Controllers\Api\AccountManager;


use App\Http\Controllers\Api\AccountManagerInterface\AccountManagerInterface;

class LibSysAccountManager implements AccountManagerInterface
{

    public function validateAccount(): AccountValidationResult
    {
        $this->user = request()->get('username');
        $this->passwd = request()->get('password');

        for ($i = 0; $i < 5 && true == $this->relContent; ++$i) {
            $res_array = $this->getContent();
            $wrong_msg = $res_array['wrong_msg'];
            if ('验证码错误(wrong check code)' == $wrong_msg) {
                continue;
            } else {
                break;
            }
        }
        switch ($wrong_msg) {
            case '对不起，密码错误，请查实！':
                $key = array(
                    'status' => self::ACCOUNT_WRONG_PASSWORD,
                    'message' => '你的用户名或密码貌似有误，请重新输入！',
                    'data' => null,
                );
                break;
            case '读者证件不存在':
                $key = array(
                    'status' => self::ACCOUNT_WRONG_PASSWORD,
                    'message' => '你的用户名或密码貌似有误，请重新输入！',
                    'data' => null,
                );
                break;
            case '验证码错误(wrong check code)':
                $key = array(
                    'status' => self::NEED_CAPTURE,
                    'message' => '服务器走神啦，请再试一下吧QAQ',
                    'data' => null,
                );
                break;
            default:  //用户通过验证时，$wrong_msg值为false
                if (false == $wrong_msg) {
                    $key = array(
                        'message' => '用户账号密码正确',
                        'data' => array(
                            'cookie' => serialize($res_array['cookie']),
                        ),
                    );
                } else {
                    $key = array(
                        'status' => self::ACCOUNT_EXPIRED,
                        'message' => '遇到了不明故障，请告诉我们吧~',
                        'data' => null,
                    );
                }
        }

        return $this->response->array($key);
    }
}