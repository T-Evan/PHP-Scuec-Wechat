<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\StudentsController;
use App\Http\Service\HelperService;
use App\Http\Service\ReadCaptcha;
use EasyWeChat\Kernel\Messages\NewsItem;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;

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

        for ($i = 0; $i < 5 && true == $this->relContent; ++$i) {
            $res_array = $this->getContent();
            $wrong_msg = $res_array['wrong_msg'];
            if ('验证码错误(wrong check code)' == $wrong_msg) {
                continue;
            } else {
                break;
            }
        }
        switch ($wrong_msg) {
            case '对不起，密码错误，请查实！':
                $key = array(
                    'status' => self::ACCOUNT_WRONG_PASSWORD,
                    'message' => '你的用户名或密码貌似有误，请重新输入！',
                    'data' => null,
                );
                break;
            case '读者证件不存在':
                $key = array(
                    'status' => self::ACCOUNT_WRONG_PASSWORD,
                    'message' => '你的用户名或密码貌似有误，请重新输入！',
                    'data' => null,
                );
                break;
            case '验证码错误(wrong check code)':
                $key = array(
                    'status' => self::NEED_CAPTURE,
                    'message' => '服务器走神啦，请再试一下吧QAQ',
                    'data' => null,
                );
                break;
            default:  //用户通过验证时，$wrong_msg值为false
                if (false == $wrong_msg) {
                    $key = array(
                        'message' => '用户账号密码正确',
                        'data' => array(
                            'cookie' => serialize($res_array['cookie']),
                        ),
                    );
                } else {
                    $key = array(
                    'status' => self::ACCOUNT_EXPIRED,
                    'message' => '遇到了不明故障，请告诉我们吧~',
                    'data' => null,
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
        $sContent = HelperService::get('http://coin.lib.scuec.edu.cn/reader/captcha.php');
        $captcha = new ReadCaptcha($sContent['res']->getBody()->getContents(), 'libary');
        $captcha = $captcha->showImg();
        $bodys = [
            'number' => $this->user,
            'passwd' => $this->passwd,
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
        $wrong_msg = HelperService::domCrawler($res['res']->getBody()->getContents(), 'filter', '#fontMsg'); //登录失败，返回页面错误信息
        $res['wrong_msg'] = $wrong_msg;

        return $res;
    }

    /**
     * 获取图书列表，列表会置于一个数组内。
     *
     * @param bool $appendCallNum 是否获取索书号（获取索书号需要爬取更多的页面，且速度相当慢）（有优化方法吗）
     *
     * @return array
     */
    public function getBooklist(Request $request = null)
    {
        $appendCallNum = $request->appendCallNum ?? true;
        $student_controller = new StudentsController();
        $cookie_array = $student_controller->cookie('lib');
        if (null == $cookie_array['data']) {
            return $cookie_array['message'];
        }
        $cookie = unserialize($cookie_array['data']);
        $res = HelperService::get(
            'http://coin.lib.scuec.edu.cn/reader/book_lst.php',
            $cookie,
            'http://coin.lib.scuec.edu.cn/reader/redr_verify.php'
        );
        /* 解析HTML获取当前借阅列表、格式化字符串后，将列表以数组的形式保存 */
        $curlResult = html_entity_decode($res['res']->getbody()->getcontents());
//        dd($curlResult);
        preg_match('/当前借阅\(.*\s*\d+/', $curlResult, $curlResultborrow);
        preg_match('/\d{1,2}/', $curlResultborrow[0], $match1);    //解析当前借阅
        preg_match('/(?<=\s)\d{1,2}/', $curlResultborrow[0], $match2);    //解析最大借阅
        $currentBorrow = $match1[0];
        $maxBorrow = $match2[0];
        if (0 == $currentBorrow) {    //判断是否有借阅
            $basicInfo = array('0', '0');
            $arrTr = null;
        } else {
            $arrTd = array();
            $basicInfo = array($currentBorrow, $maxBorrow);
            $trSet = HelperService::domCrawler($curlResult, 'filter', '.table_line');

            //心情不好，瞎写一通
            preg_match_all('/\S+/', $trSet, $match3);    //解析图书信息
            preg_match_all('/class="blue"\shref="(.*)">/', $curlResult, $marcHrefArr);    //解析marc_no
            preg_match_all('/getInLib\((.*)\)/', $curlResult, $getInLib);    //解析getInLib
            unset($getInLib[1][3]);
            foreach ($getInLib[1] as $value) {
                preg_match_all('/\'(.*?)\'/', $value, $res);    //解析getInLib
                $LibArr[] = $res;
            }
            $arrTd[] = $match3;
            $arrTdArr = array();

            for ($i = 8 , $j = 0; $i < count($arrTd[0][0]); $i += 9, $j++) {
                $arrTdArr[] = array_slice($arrTd[0][0], $i, 9);
                $arrTdArr[$j][] = $marcHrefArr[1][$j];
                $arrTdArr[$j] = array_merge($arrTdArr[$j], $LibArr[$j][1]);
            }

            foreach ($arrTdArr as $arrArr) {
//            preg_match('/marc_no=(.*)/', $marcHref, $match4);    //解析图书marcno
                $arrArr[9] = $this->getBookCallNumber($arrArr[9]);  //解析索书号
                $translated = array(
                    'barcode' => $arrArr[0],
                    'name' => $arrArr[1].$arrArr[2].$arrArr[3],
                    'borrow_date' => $arrArr[4],
                    'due_date' => $arrArr[5],
                    'renew_times' => (int) $arrArr[6],
                    'location' => $arrArr[7],
                    'attachment' => $arrArr[8],
                    'call_number' => $arrArr[9],
                    'renew_check_code' => $arrArr[11],
                    'index' => $arrArr[12],
                    'renewable' => false,
                    'overdue' => false,
                    'overdue_day_count' => null,
                );
                $dueDateTimestamp = strtotime($translated['due_date']);

                if (false !== $dueDateTimestamp) {
                    $remainingDays = (int) floor((($dueDateTimestamp - time()) / 86400)) + 1;
                    if ($remainingDays <= 10) {
                        if ($remainingDays >= 0) {
                            if (0 == $translated['renew_times']) {
                                $translated['renewable'] = true;
                            }
                        } else {
                            $translated['overdue'] = true;
                            $overdueDays = -($remainingDays);
                            $translated['overdue_day_count'] = $overdueDays;
                        }
                    }
                } else {
                    // log
                }
                $arrTr[] = $translated;
            }

            $bookList = array($basicInfo, $arrTr);
        }

        $redis = Redis::connection('libary');
        $redis->setex(
            'libary_'.app('wechat_common')->openid,
            config('app.libary_cookie_cache_time'),
            json_encode($bookList)
        ); //缓存借阅信息一小时

        $key = array(
            'status' => self::SUCCESS,
            'message' => __CLASS__.': get book list successfully.',
            'data' => $bookList,
        );

        return $this->response->array($key);
    }

    /**
     * get a formed message which can send to the user directly.
     *
     * @return string
     *
     */
    public function getMessage()
    {
        /*FIXME:据说小说不能续借。想办法判断哪些书是小说，然后加入可续借判断算法中，并把续借算法独立出来作为一个类方法。*/
        /*FIXME:光盘的罚款是每日0.5元，而不是0.1元。罚金计算算法需要加入对光盘的判断。*/
        $redis = Redis::connection('libary');
        $libaryCache = $redis->get('libary_'.app('wechat_common')->openid);
        if (false != $libaryCache) {
            $bookList = json_decode($libaryCache, true);
        } else {
            $bookList = $this->api->post('students/lib/booklist'); //dingo内部调用
            if (!is_array($bookList)) {
                switch ($bookList) {
                    case '用户不存在':
                        $news = "绑定账号后即可查询借阅信息。/::,@\n请先".
                            HelperService::getBindingLink('lib');
                        break;
                    case '用户信息有误':
                        $news = '你绑定的账号信息貌似有误 /:P-( 需要重新'.
                            HelperService::getBindingLink('lib');
                        break;
                }

                return $news;
            }
            $bookList = $bookList['data'];
        }
        $borrowAmount = $bookList[0][0];
        $totalAmount = $bookList[0][1];
        if ($borrowAmount > 0) {
            $resultStr = '';
            $renewable = '';
            $overdue = '';
            foreach ($bookList[1] as $key => $bookInfo) {
                if (true == $bookInfo['renewable']) {
                    $renewable .= "\n\n续借编号: {$bookInfo['index']}"
                        ."\n书名: ".$bookInfo['name']
                        ."\n索书号: ".$bookInfo['call_number']
                        ."\n借阅日期: ".$bookInfo['borrow_date']
                        ."\n应还日期: ".$bookInfo['due_date'];
                } elseif (true == $bookInfo['overdue']) {
                    $overdue .= "\n\n续借编号: [超期不可续借]"
                        ."\n书名: ".$bookInfo['name']
                        ."\n索书号: ".$bookInfo['call_number']
                        ."\n借阅日期: ".$bookInfo['borrow_date']
                        ."\n应还日期: ".$bookInfo['due_date']
                        ."\n罚金：".($bookInfo['overdue_day_count'] * 0.1).'元 (仅供参考)';
                } else {
                    $resultStr .= "\n\n续借编号: [暂不可续借]"
                        ."\n书名: ".$bookInfo['name']
                        ."\n索书号: ".$bookInfo['call_number']
                        ."\n借阅日期: ".$bookInfo['borrow_date']
                        ."\n应还日期: ".$bookInfo['due_date'];
                }
            }
        } else {
            $resultStr = "你当前没有借阅书籍哦。快去借几本书来看吧。立身以立学为先，立学以读书为本。\n----\n如果该结果有误，可以\"@程序员\"反馈问题哦~";

            return $resultStr;
        }
        $resultStr .= (0 != strlen($renewable)) ? "\n\n  ---- 可续借的图书 ----".$renewable : $renewable;
        $resultStr .= (0 != strlen($overdue)) ? "\n\n  ---- 已超期的图书 ----".$overdue : $overdue;
        $resultStr = "当前借阅 ({$borrowAmount}/{$totalAmount})\n".$resultStr."\n\n关键词\"续借+续借编号\"可以续借对应的书籍。如果遇到问题，记得@程序员哦。";
        /*长度限制，暂时返回纯文本
        $news = [
            new NewsItem(
                [
                    'title' => "当前借阅 ({$borrowAmount}/{$totalAmount})",
                    'description' => trim($resultStr)."\n\n关键词\"续借+续借编号\"可以续借对应的书籍。如果遇到问题，记得@程序员哦。",
                ]
            ),
        ];
        */

        return $resultStr;
    }

    /**
     * 获取索书号.
     *
     * @param $relUrl
     *
     * @return mixed
     */
    private function getBookCallNumber($relUrl)
    {
        /*FIXME:对于含光盘的书本，以下获取索书号的方法会获取到光盘对应的索书号，而不是书本本身的索书号。*/
        $marc_num = substr($relUrl, -10);
        /* check if cached*/
        $callNumber = Redis::hGet('library:call_numbers', 'library:'.$marc_num);
        if (null !== $callNumber) {
            // echo "[debug]cached callNumber found:{$callNumber} in library:{$marc_num}\n";
            return $callNumber;
        }
        /* if either redisHandle or cache is existed */
        $strResult = HelperService::get('http://coin.lib.scuec.edu.cn/reader/'.$relUrl);

        $html = $strResult['res']->getbody()->getcontents();
        $marcClickPos = null;
        $dlNo = 3;
        while (false == $marcClickPos) {  //不能准确抓取isbn的位置?暂时不晓得好的写法
            $rule = '//*[@id="item_detail"]/dl['.$dlNo.']/dd';
            $marcClick = HelperService::domCrawler($html, 'filterXPath', $rule);
            $marcClickPos = strpos($marcClick, '/');
            ++$dlNo;
        }
        $callNumber = substr($marcClick, 0, strpos($marcClick, '/'));
        if (false != $strResult && false != $callNumber) {
            Redis::hSet('library:call_numbers', 'library:'.$marc_num, $callNumber);

            return $callNumber;
        }

        return null;
    }

    public function getLoginContent()
    {
        return $this->getBookCallNumber('../opac/item.php?marc_no=3673716d53646a2f33743076574c644d776c344547773d3d');
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
        $_name = $html->find('font[color=blue]', 0)->plaintext;
        if ($_name === $this->name && '' != $_name && '' != $this->name) {  //有姓名
            return 1;
        } else {   //无姓名
            $str = $html->find('font[color=red]', 0)->plaintext;
            if ('如果认证失败，您将不能使用我的图书馆功能' === $str) {
                return 1;
            } else {
                return 0;
            }
        }
    }
}
