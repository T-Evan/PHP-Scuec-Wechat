<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\AccountManager\EduSys\LibSysAccount;
use App\Http\Controllers\Api\AccountManager\LibSysAccountManager;
use Tests\TestCase;

class LibSysAccountManagerTest extends TestCase
{
    public function userAccountProvider()
    {
        return [
            ['201621094040', '201621094040'],
        ];
    }

    public function openidProvider()
    {
        return [
            ['oULq3utesttesttesttesttest12']
        ];
    }

    /**
     * @dataProvider userAccountProvider
     * @param string $account
     * @param string $password
     * @return void
     */
    public function testValidateAccount(string $account, string $password)
    {
        $libAccount = new LibSysAccount();
        $libAccount->setAccount($account);
        $libAccount->setPassword($password);
        $libSysAccountManager = new LibSysAccountManager();
        $validationResult = $libSysAccountManager->validateAccount($libAccount);
        print_r($validationResult->getCookie());
        print_r($validationResult->getCode().":".$validationResult->getMsg());
        $this->assertFalse($validationResult->isFailed());
    }

    /**
     * @dataProvider openidProvider
     * @param string $openid
     */
    public function testGetCookie(string $openid)
    {
        $libSysAccountManager = new LibSysAccountManager();
        $cookieJar = $libSysAccountManager->getCookie($openid);
        $this->assertNotNull($cookieJar);
    }
}
