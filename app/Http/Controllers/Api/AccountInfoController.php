<?php

namespace App\Http\Controllers\Api;

use App\Http\Service\HelperService;
use Illuminate\Http\Request;
use Symfony\Component\DomCrawler\Crawler;

class AccountInfoController extends Controller
{
    /**
     * 参考github Restful 状态码设置
     * 200表示账号密码认证通过
     * 422表示认证信息缺失或不正确
     * 401用来表示校验错误
     * 410表示请求资源已不存在，代指学生账号已过期
     * 503表示服务暂不可用，代指Ip被冻结
     * 504表示网关超时，代指学校网站维护
     */

    const ACCOUNT_WRONG_PASSWORD = 422;
    const NEED_CAPTURE = 202;
    const ACCOUNT_EXPIRED = 410;
    const TIMEOUT = 504;
    const FREEZE = 503;
    const SUCCESS = 200;

    /**
     * Notes:
     * @param $userInfoArray:携带账号密码的数组
     * @return array|null|string
     * 该函数主要用来判断用户是否为在校学生
     * 以及获取可直接访问办事大厅部分服务的有效cookie
     * 为了提高效率，使用了Guzzle扩展完成curl操作
     * 因此返回的cookie为Guzzle扩展中的Cookiejar对象(转化成合法cookie比较耗时，先这样吧
     * 该对象可经反序列化后直接在Guzzle中使用
     */

    public function judgeAccount(Request $request)
    {
        $userInfoArray=$request->toArray();
        $res = HelperService::get('http://id.scuec.edu.cn/authserver/login');
        $data = $res['res']->getBody()->getContents();
        $cookie_jar= $res['cookie'];

        $crawler = new Crawler();
        $crawler->addHtmlContent($data);
        for ($i = 10; $i < 15; $i++) {
            $key = $crawler->filter('#casLoginForm > input[type="hidden"]:nth-child(' . $i . ')')
                ->attr('name');
            $value = $crawler->filter('#casLoginForm > input[type="hidden"]:nth-child(' . $i . ')')
                ->attr('value');
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
        $user_name = HelperService::domCrawler($data, 'filterXPath', '//*[@class="auth_username"]/span/span'); //尝试从登录后页面获取姓名，判断是否登录成功
        if ($user_name) {
            $key = array(
                'message' => "user valid and get cookie successfully",
                'data' => array(
                    'cookie' => serialize($res['cookie'])
                )
            );
            return $this->response->array($key)->setStatusCode(self::SUCCESS);
        } else {
            $wrong_msg = HelperService::domCrawler($data, 'filter', '#msg'); //登录失败，返回页面错误信息
            switch ($wrong_msg) {
                case '您提供的用户名或者密码有误':
                    $key = array(
                        'message' => "你的用户名或密码貌似有误，请重新输入！",
                        'data' => null
                    );
                    return $this->response->array($key)->setStatusCode(self::ACCOUNT_WRONG_PASSWORD);
                    break;
                case '请输入验证码':
                    $key = array(
                        'message' => "你输入错误次数过多，请尝试登陆官网认证身份后，重新进行绑定！",
                        'data' => null
                    );
                    return $this->response->array($key)->setStatusCode(self::NEED_CAPTURE);
                    break;
                case 'expired':
                    return $this->response->array([
                        'message' => "account expired",
                        'data' => null])
                        ->setStatusCode(self::ACCOUNT_EXPIRED);
                    break;
            }
        }
    }

    public function getStudentName($userInfoArray)
    {
        $res = $this->judgeAccount($userInfoArray);
        $cookie = unserialize($res['data']['cookie']);

        $res = HelperService::get(
            'http://id.scuec.edu.cn/authserver/login?goto=http%3A%2F%2Fssfw.scuec.edu.cn%2Fssfw%2Fj_spring_ids_security_check',
            $cookie,
            'http://ssfw.scuec.edu.cn/ssfw/index.do'
        );

        $jar1 = $res['cookie'];

        $res = HelperService::get(
            'http://ssfw.scuec.edu.cn/ssfw/pkgl/kcbxx/4/2017-2018-2.do?flag=4&xnxqdm=2017-2018-2',
            $jar1,
            'http://ssfw.scuec.edu.cn/ssfw/index.do'
        );
        return $res['res']->getbody();
    }
}
