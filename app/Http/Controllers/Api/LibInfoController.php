<?php

namespace App\Http\Controllers\Api;

use App\Http\Service\HelperService;
use App\Http\Service\ReadCaptcha;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LibInfoController extends Controller
{
    const ACCOUNT_WRONG_PASSWORD = 422;
    const NEED_CAPTURE = 401;
    const ACCOUNT_EXPIRED = 410;
    const TIMEOUT = 504;
    const FREEZE = 503;
    const SUCCESS = 200;

    private $user;
    private $passwd;
    private $result;
    private $relContent = true;

    /*
     * 请求一个验证码并获取其cookie
     */
    /**
     * @return mixed
     */
    public function judgeAccount(Request $request)
    {
        $this->user = $request->username;
        $this->passwd = $request->password;

        for ($i = 0; $i < 5 && $this->relContent == true; $i++) {
            $res_array = $this->getContent();
            $wrong_msg = $res_array['wrong_msg'];
            if ($wrong_msg=="验证码错误(wrong check code)") {
                continue;
            } else {
                break;
            }
        }
        switch ($wrong_msg) {
            case '对不起，密码错误，请查实！':
                $key = array(
                    'status' => self::ACCOUNT_WRONG_PASSWORD,
                    'message' => "你的用户名或密码貌似有误，请重新输入！",
                    'data' => null
                );
                break;
            case '读者证件不存在':
                $key = array(
                    'status' => self::ACCOUNT_WRONG_PASSWORD,
                    'message' => "你的用户名或密码貌似有误，请重新输入！",
                    'data' => null
                );
                break;
            case '验证码错误(wrong check code)':
                $key = array(
                    'status' => self::NEED_CAPTURE,
                    'message' => "服务器走神啦，请再试一下吧QAQ",
                    'data' => null
                );
                break;
            default:  //用户通过验证时，$wrong_msg值为false
                if ($wrong_msg == false) {
                    $key = array(
                        'message' => "用户账号密码正确",
                        'data' => array(
                            'cookie' => serialize($res_array['cookie'])
                        )
                    );
                } else {
                    $key = array(
                    'status' => self::ACCOUNT_EXPIRED,
                    'message' => "遇到了不明故障，请告诉我们吧~",
                    'data' => null
                    );
                }
        }
        return $this->response->array($key);
    }

    /*
     * 获取登录页面内容
     */
    private function getContent()
    {
        $sContent = HelperService::get("http://coin.lib.scuec.edu.cn/reader/captcha.php");
        $captcha = new ReadCaptcha($sContent['res']->getBody()->getContents());
        $captcha = $captcha->showImg();
        $bodys=[
            'number' => $this->user,
            'passwd' => $this->passwd,
            'captcha' => $captcha,
            'select' => 'cert_no',
            'returnUrl' => '',
        ];
        $res = HelperService::post(
            $bodys,
            "http://coin.lib.scuec.edu.cn/reader/redr_verify.php",
            'form_params',
            null,
            $sContent['cookie']
        );
        $wrong_msg = HelperService::domCrawler($res['res']->getBody()->getContents(), 'filter', '#fontMsg'); //登录失败，返回页面错误信息
        $res['wrong_msg']=$wrong_msg;
        return $res;
    }

    public function getLoginContent()
    {
        return $this->result;
    }

    /*
     * 验证姓名
     * 有两种情况：
     * 一种是已经做过姓名认证的，那么需要匹配姓名；
     * 一种是没做过姓名认证的，则不需要匹配姓名，并且提示用户做姓名认证
     */
    public function validateUser()
    {
        $html = str_get_html($this->getLoginContent());
        $_name = $html->find("font[color=blue]", 0)->plaintext;
        if ($_name === $this->name && $_name != "" && $this->name != "") {  //有姓名
            return 1;
        } else {   //无姓名
            $str = $html->find("font[color=red]", 0)->plaintext;
            if ($str === "如果认证失败，您将不能使用我的图书馆功能") {
                return 1;
            } else {
                return 0;
            }
        }
    }
}
