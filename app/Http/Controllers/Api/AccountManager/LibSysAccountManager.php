<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 2018/10/27
 * Time: 11:59 AM
 */

namespace App\Http\Controllers\Api\AccountManager;


use App\Http\Controllers\Api\AccountManager\EduSys\LibSysAccount;
use App\Http\Controllers\Api\AccountManagerInterface\AccountManagerInterface;
use App\Http\Service\AccountService\Facades\Account;
use App\Http\Service\HelperService;
use App\Http\Service\ReadCaptcha;
use App\Models\StudentInfo;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

class LibSysAccountManager implements AccountManagerInterface
{
    protected $username;
    protected $password;

    public function validateAccount(BaseAccount $account): AccountValidationResult
    {
        $this->username = $account->getAccount();
        $this->password = $account->getPassword();

        $res_array = [];
        for ($i = 0; $i < 5; ++$i) {
            $res_array = $this->tryLogin();
            $wrong_msg = $res_array['wrong_msg'];
            if (strpos($wrong_msg, '验证码错误(wrong check code)') !== false) {
                continue;
            } else {
                break;
            }
        }
        $returnMsg = '用户账号密码正确';
        $statusCode = self::STATUS_SUCCESS;
        $failed = false;
        if (isset($wrong_msg) && $wrong_msg != false) {
            $failed = true;
            $statusCode = self::STATUS_ACCOUNT_VERIFIED_FAILED;
            if (strpos($wrong_msg, '对不起，密码错误，请查实！') !== false) {
                $returnMsg = '你的用户名或密码貌似有误，请重新输入！';
            } elseif (strpos($wrong_msg, '读者证件不存在') !== false) {
                $returnMsg = '你的用户名或密码貌似有误，请重新输入！';
            } elseif (strpos($wrong_msg, '验证码错误(wrong check code)') !== false) {
                $returnMsg = '服务器走神啦，请再试一下吧QAQ';
            } else {
                $statusCode = self::STATUS_ACCOUNT_EXPIRED;
                $returnMsg = '遇到了不明故障，请告诉我们吧~';
            }
        }

        if (!$failed) {
            // save cookie
            $redisKey = $this->getLibRedisKey(Account::getOpenid());
            $cookieJarSerialized = serialize($res_array['cookie']);
            $ttl = config('app.library_cookie_cache_time');
            Redis::connection('library')->set($redisKey, $cookieJarSerialized);
            Redis::connection('library')->expire($redisKey, $ttl);
        }

        return new AccountValidationResult($failed, $statusCode, $res_array['cookie'], $returnMsg);
    }

    public function getCookie(string $openid): CookieJar
    {
        $redisKey = self::getLibRedisKey($openid);
        $cookieCache = self::redisConn()->get($redisKey);
        if ($cookieCache) {
            return unserialize($cookieCache);
        }
        // if cache is expired
        $studentInfo = StudentInfo::where('openid', $openid)->first();
        if (!$studentInfo || !$studentInfo->account || !$studentInfo->lib_password) {
            return null;
        }
        $libAccount = new LibSysAccount();
        $libAccount->setAccount(decrypt($studentInfo->account));
        $libAccount->setPassword(decrypt($studentInfo->lib_password));
        $validationResult = $this->validateAccount($libAccount);
        if ($validationResult->isFailed()) {
            return null;
        }
        return $validationResult->getCookie();
    }

    protected function tryLogin()
    {
        $sContent = HelperService::get('http://coin.lib.scuec.edu.cn/reader/captcha.php');
        $captcha = new ReadCaptcha($sContent['res']->getBody()->getContents(), 'library');
        $captcha = $captcha->showImg();
        $bodys = [
            'number' => $this->username,
            'passwd' => $this->password,
            'captcha' => $captcha,
            'select' => 'cert_no',
            'returnUrl' => '',
        ];
        $res = HelperService::post(
            $bodys,
            'http://coin.lib.scuec.edu.cn/reader/redr_verify.php',
            'form_params',
            null,
            $sContent['cookie']
        );
        //登录失败，返回页面错误信息
        $wrong_msg = HelperService::domCrawler(
            $res['res']->getBody()->getContents(),
            'filter',
            '#fontMsg');
        $res['wrong_msg'] = $wrong_msg;

        return $res;
    }

    public static function getLibRedisKey(string $openid): string
    {
        return "lib_$openid";
    }

    public static function redisConn(): Connection
    {
        return Redis::connection('library');
    }
}