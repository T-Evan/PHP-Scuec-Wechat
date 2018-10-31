<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\AccountManager\EduSys\EduSysAccount;
use App\Http\Controllers\Api\AccountManager\EduSysAccountManager;
use App\Http\Controllers\Api\AccountManager\Exceptions\AccountValidationFailedException;
use GuzzleHttp\Exception\GuzzleException;
use Tests\TestCase;

class EduSysAccountManagerTest extends TestCase
{
    public function accountProviders() {
        return [
            ['201621094040', '.dl151713']
        ];
    }

    /**
     * @return array
     */
    public function openidProvider()
    {
        return [
            ['oULq3utesttesttesttesttest12']
        ];
    }

    /**
     * @dataProvider accountProviders
     * @param string $uid
     * @param string $password
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function testValidateAccount(string $uid, string $password)
    {
        $accountManager = new EduSysAccountManager();
        $account = new EduSysAccount();
        $account->setAccount($uid);
        $account->setPassword($password);
        $validationResult = $accountManager->validateAccount($account);
        print($validationResult->getMsg());
        $this->assertFalse($validationResult->isFailed());
    }

    /**
     * @dataProvider openidProvider
     * @param string $openid
     */
    public function testGetCookie(string $openid)
    {
        $eduAccountManager = new EduSysAccountManager();
        try {
            $cookieJar = $eduAccountManager->getCookie($openid);
            var_dump($cookieJar);
            $this->assertNotNull($cookieJar);
        } catch (AccountValidationFailedException $e) {
            echo "account validation failed: ".$e->getMessage();
            return;
        } catch (GuzzleException $e) {
            echo "request failed: ".$e->getMessage();
            return;
        }
    }
}
