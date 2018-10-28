<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 2018/10/27
 * Time: 11:08 AM
 */

namespace App\Http\Controllers\Api\AccountManager;


class AccountManagerFactory
{
    public static function getAccountManager(string $manager)
    {
        switch ($manager) {
            case 'ssfw':
                return new EduSysAccountManager();
                break;
            case 'lib':
//                $result = $this->api->post('students/lib', $user_info_array); //dingo内部调用
                break;
            case 'lab':
//                $result = $this->api->post('students/lab', $user_info_array);
                break;
            default:
//                $result = ['message' => '错误的提交'];
                break;
        }
    }
}