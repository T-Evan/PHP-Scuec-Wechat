<?php
/**
 * Created by PhpStorm.
 * User: yaphper
 * Date: 2018/10/27
 * Time: 11:30 AM
 */

namespace App\Http\Controllers\Api\AccountManager;


use App\Http\Controllers\Api\AccountManagerInterface\AccountManagerInterface;
use App\Http\Service\HelperService;
use Symfony\Component\DomCrawler\Crawler;

class EduSysAccountManager implements AccountManagerInterface
{

    public function validateAccount(): AccountValidationResult
    {
        $userInfoArray = request()->toArray();
        $res = HelperService::get('http://id.scuec.edu.cn/authserver/login');
        $data = $res['res']->getBody()->getContents();
        $cookie_jar = $res['cookie'];

        $crawler = new Crawler();
        $crawler->addHtmlContent($data);
        for ($i = 10; $i < 15; ++$i) {
            $key = $crawler->filter('#casLoginForm > input[type="hidden"]:nth-child('.$i.')')
                    ->attr('name') ?? null;
            $value = $crawler->filter('#casLoginForm > input[type="hidden"]:nth-child('.$i.')')
                    ->attr('value') ?? null;
            $userInfoArray[$key] = $value;
        }

        $res = HelperService::post(
            $userInfoArray,
            'http://id.scuec.edu.cn/authserver/login?goto=http%3A%2F%2Fssfw.scuec.edu.cn%2Fssfw%2Fj_spring_ids_security_check',
            'form_params',
            'http://ehall.scuec.edu.cn/new/index.html',
            $cookie_jar
        );

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

        return new AccountValidationResult(
            false,
            self::STATUS_SUCCESS,
            $res['cookie'],
            'success'
        );
    }
}