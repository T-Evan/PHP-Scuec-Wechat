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
                return new LibSysAccountManager();
                break;
            case 'lab':
                return new LabSysAccountManager();
                break;
        }
        return null;
    }
}