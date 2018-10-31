<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 2018/10/27
 * Time: 11:30 AM
 */

namespace App\Http\Controllers\Api\AccountManager;


use App\Http\Controllers\Api\AccountManager\EduSys\EduSysAccount;
use App\Http\Controllers\Api\AccountManager\Exceptions\AccountValidationFailedException;
use App\Http\Controllers\Api\AccountManagerInterface\AccountManagerInterface;
use App\Http\Service\HelperService;
use App\Models\StudentInfo;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\DomCrawler\Crawler;

class EduSysAccountManager implements AccountManagerInterface
{

    /**
     * @param BaseAccount $accountInfo
     * @return AccountValidationResult
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function validateAccount(BaseAccount $accountInfo): AccountValidationResult
    {
        $res = HelperService::get('http://id.scuec.edu.cn/authserver/login');
        $data = $res['res']->getBody()->getContents();
        $cookie_jar = $res['cookie'];

        $crawler = new Crawler();
        $crawler->addHtmlContent($data);
        $loginPostData = [
            'username' => $accountInfo->getAccount(),
            'password' => $accountInfo->getPassword()
        ];
        for ($i = 10; $i < 15; ++$i) {
            $key = $crawler->filter('#casLoginForm > input[type="hidden"]:nth-child('.$i.')')
                    ->attr('name') ?? null;
            $value = $crawler->filter('#casLoginForm > input[type="hidden"]:nth-child('.$i.')')
                    ->attr('value') ?? null;
            $loginPostData[$key] = $value;
        }

        $res = HelperService::post(
            $loginPostData,
            'http://id.scuec.edu.cn/authserver/login?goto=http%3A%2F%2Fssfw.scuec.edu.cn%2Fssfw%2Fj_spring_ids_security_check',
            'form_params',
            'http://ehall.scuec.edu.cn/new/index.html',
            $cookie_jar
        );

        if (!is_array($res)) {
            return new AccountValidationResult(
                true,
                self::STATUS_REQUEST_FAILED,
                null,
                '服务请求错误，请稍后重试'.$res
            );
        }

        $data = $res['res']->getBody()->getContents();
        //尝试从登录后页面获取姓名，判断是否登录成功
        $user_name = HelperService::domCrawler($data,
            'filterXPath',
            '//*[@class="auth_username"]/span/span');

        if (!$user_name) {
            //登录失败，返回页面错误信息
            $wrong_msg = HelperService::domCrawler($data, 'filter', '#msg');
            switch ($wrong_msg) {
                case '您提供的用户名或者密码有误':
                    return new AccountValidationResult(
                        true,
                        self::STATUS_ACCOUNT_VERIFIED_FAILED,
                        null,
                        '你的用户名或密码貌似有误，请重新输入！'
                    );
                    break;

                case '请输入验证码':
                    return new AccountValidationResult(
                        true,
                        self::STATUS_TOO_FREQUENT_ACCESS,
                        null,
                        '你尝试的次数过多，请点击链接进行身份认证后，重新进行绑定！'
                    );
                    break;

                case 'expired':
                    return new AccountValidationResult(
                        true,
                        self::STATUS_ACCOUNT_EXPIRED,
                        null,
                        '账户过期'
                    );
                    break;
            }
        }

        // 将cookie序列化并写入redis
        $cookieString = serialize($cookie_jar);
        Redis::connection('default')
            ->setex(self::getSSFWRedisKey($accountInfo->getOpenid()),
                env('COOKIE_TIME', 3600),
                $cookieString);

        return new AccountValidationResult(
            false,
            self::STATUS_SUCCESS,
            $res['cookie'],
            'success'
        );
    }

    /**
     * @param string $openid
     * @return CookieJar
     * @throws GuzzleException
     * @throws AccountValidationFailedException
     */
    public function getCookie(string $openid): CookieJar
    {
        $cookieString = Redis::connection('default')->get(self::getSSFWRedisKey($openid));
        if ($cookieString) {
            return unserialize($cookieString);
        }
        $studentInfo = StudentInfo::where('openid', $openid)->first();
        if (!$studentInfo || !$studentInfo->account || !$studentInfo->ssfw_password) {
            return null;
        }
        $eduSysAccount = new EduSysAccount();
        $eduSysAccount->setAccount($studentInfo->account);
        $eduSysAccount->setPassword(decrypt($studentInfo->ssfw_password));
        $validationResult = $this->validateAccount($eduSysAccount);
        if ($validationResult->isFailed()) {
            throw new AccountValidationFailedException(
                $validationResult->getMsg(),
                $validationResult->getCode());
        }
        return $validationResult->getCookie();
    }

    /**
     * @param string $openid
     * @return string
     */
    public static function getSSFWRedisKey(string $openid): string
    {
        return "ssfw_$openid";
    }
}