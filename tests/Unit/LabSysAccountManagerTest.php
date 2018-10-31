<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\AccountManager\BaseAccount;
use App\Http\Controllers\Api\AccountManager\Exceptions\AccountNotBoundException;
use App\Http\Controllers\Api\AccountManager\Exceptions\AccountValidationFailedException;
use App\Http\Controllers\Api\AccountManager\LabSysAccountManager;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LabSysAccountManagerTest extends TestCase
{
    public function userAccountProvider()
    {
        return [
            ['201621094040', '.dl151713'],
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
        $labAccount = new BaseAccount();
        $labAccount->setAccount($account);
        $labAccount->setPassword($password);
        $libSysAccountManager = new LabSysAccountManager();
        $validationResult = $libSysAccountManager->validateAccount($labAccount);
        $this->assertFalse($validationResult->isFailed());
    }

    /**
     * @dataProvider openidProvider
     * @param string $openid
     * @throws AccountNotBoundException
     */
    public function testGetCookie(string $openid)
    {
        $labSysAccountManager = new LabSysAccountManager();
        try {
            $cookieJar = $labSysAccountManager->getCookie($openid);
        } catch (AccountValidationFailedException $e) {
            print_r($e->getMessage());
            return;
        }
        $this->assertNotNull($cookieJar);
    }
}
