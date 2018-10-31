<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 2018/10/31
 * Time: 2:54 PM
 */

namespace App\Http\Controllers\Api\AccountManager;


use App\Http\Controllers\Api\AccountManager\Exceptions\AccountNotBoundException;
use App\Http\Controllers\Api\AccountManager\Exceptions\AccountValidationFailedException;
use App\Http\Controllers\Api\AccountManager\LabSys\LabAccount;
use App\Http\Controllers\Api\AccountManagerInterface\AccountManagerInterface;
use App\Http\Service\HelperService;
use App\Models\StudentInfo;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

class LabSysAccountManager implements AccountManagerInterface
{

    public function validateAccount(BaseAccount $account): AccountValidationResult
    {
        $loginPostData = [
            'mytype' => 'student',
            'myid' => $account->getAccount(),
            'mypasswd' => $account->getPassword(),
            'OK' => '%C8%B7%B6%A8'
        ];
        try {
            $loginResponse = HelperService::post(
                $loginPostData,
                'http://labsystem.scuec.edu.cn/login.php',
                'form_params',
                'http://labsystem.scuec.edu.cn'
            );
        } catch (GuzzleException $e) {
            return new AccountValidationResult(
                true,
                self::STATUS_REQUEST_FAILED,
                null,
                $e->getMessage()
            );
        }
        $responseContent = $loginResponse['res']->getBody()->getContents();
        if (strpos($responseContent, "main") === false) {
            print_r($responseContent);
            return new AccountValidationResult(
                true,
                self::STATUS_ACCOUNT_VERIFIED_FAILED,
                null,
                '账户或密码错误'
            );
        }

        return new AccountValidationResult(
            false,
            self::STATUS_SUCCESS,
            $loginResponse['cookie']
        );
    }

    /**
     * @param string $openid
     * @return CookieJar
     * @throws AccountNotBoundException
     * @throws AccountValidationFailedException
     */
    public function getCookie(string $openid): CookieJar
    {
        $redisKey = self::getLabRedisKey($openid);
        $redisConn = self::getRedisConn();
        $redisCache = $redisConn->get($redisKey);
        if ($redisCache) {
            return unserialize($redisCache);
        }
        // cache is expired
        $studentInfo = StudentInfo::where('openid', $openid)->first();
        if (!$studentInfo || !$studentInfo->account || !$studentInfo->lab_password) {
            throw new AccountNotBoundException(
                "大学物理实验账户未绑定",
                self::STATUS_ACCOUNT_NOT_BOUND
            );
        }
        $labAccount = new LabAccount();
        $labAccount->setAccount($studentInfo->account);
        $labAccount->setPassword(decrypt($studentInfo->lab_password));
        $validationResult = $this->validateAccount($labAccount);
        if ($validationResult->isFailed()) {
            throw new AccountValidationFailedException(
                $validationResult->getMsg(),
                self::STATUS_ACCOUNT_VERIFIED_FAILED
            );
        }
        return $validationResult->getCookie();
    }

    public static function getLabRedisKey(string $openid)
    {
        return 'lab_'.$openid;
    }

    public static function getRedisConn(): Connection
    {
        return Redis::connection('lab');
    }
}