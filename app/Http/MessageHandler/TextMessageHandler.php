<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/16
 * Time: 19:27.
 */

namespace App\Http\MessageHandler;

use App\Http\Controllers\Api\AccountInfoController;
use App\Http\Controllers\Api\LibInfoController;
use App\Http\Controllers\Api\WakeSignDetailInfosController;
use App\Http\Service\HelperService;
use App\Http\Service\KuaiDiApiService;
use App\Http\Service\OuterApiService;
use App\Models\Common;
use EasyWeChat\Kernel\Contracts\EventHandlerInterface;
use EasyWeChat\Kernel\Messages\News;
use EasyWeChat\Kernel\Messages\NewsItem;

class TextMessageHandler implements EventHandlerInterface
{
    public function handle($message = null)
    {
        $keyword = trim($message['Content']);
        $switchKey = $this->dealStr($keyword);

        switch ($switchKey) {
            case 'help':
                $content = $this->helpStr();     //帮助信息文本
                return $content;  //标准格式回复
                break;
            case 'weather':
                return OuterApiService::weather();
                break;
            case 'bus':
                $content = '<a href="http://m.amap.com/search/view/keywords='.$keyword.'">〖高德地图-'.$keyword.'〗</a>'; //暂未找到免费公交查询接口,暂时跳转到高德地图查看
                return $content;
                break;
            case 'train':
                $trainNum = HelperService::getContent($keyword, '火车');   // 得到车次
                return OuterApiService::train($trainNum);
                break;
            case 'kuaidi':
                $kuaidi_num = HelperService::getContent($keyword, '快递');  // 得到快递单号
                $kuaidi_service = new KuaiDiApiService($kuaidi_num);
                $content = $kuaidi_service->kuaiDi();

                return $content;
                break;
            case 'map':
                $items = [
                    new NewsItem(
                        [
                            'title' => '中南民族大学地图',
                            'description' => "点击进入民大地图\n周边搜索功能，囊括餐饮、娱乐、购物等周边生活信息，轻松掌控城市生活。\n线路搜索功能，轻松规划出行线路",
                            'url' => "https://m.expoon.com/qjjx/xuexiao/7onuyhthawb.html",
                            'image' => config('app.base_url').'/img/baidumap.jpg',
                        ]
                    ),
                ];

                return new News($items);
                break;
            case 'translate':
                $word = HelperService::getContent($keyword, '翻译');   // 获得需要翻译的文本
                return OuterApiService::translate($word);
                break;
            case 'fuxiu':
                $items = [
                    new NewsItem(
                        [
                            'title' => '纯干货 | 十校联盟辅修事项',
                            'description' => '小塔为你整理的辅修干货在这里呀，希望可以帮到要辅修的同学哦~',
                            'url' => 'http://mp.weixin.qq.com/s/xYNhsFdgdWlxP4b_lZd_JQ',
                            'image' => config('app.base_url').'/img/fuxiu.jpg',
                        ]
                    ),
                ];

                return new News($items);
                break;
            case 'zhuxue':
                $items = [
                    new NewsItem(
                        [
                            'title' => '干货 | 在大学一定要多拿几个奖',
                            'description' => '奖/助学金大汇总，奖项多到你想不到！',
                            'url' => 'http://mp.weixin.qq.com/s/vxHhH_mntbbcRioBSbor2A',
                            'image' => config('app.base_url').'/img/zhuxue.jpg',
                        ]
                    ),
                ];

                return new News($items);
                break;
            case 'SchoolCalendar':
                $items = [
                    new NewsItem(
                        [
                            'title' => '时刻表 | 民大生存必备，你要的时间都在这里！',
                            'description' => '时刻表每学期一更，维持民大最新的各地点记录～',
                            'url' => 'http://mp.weixin.qq.com/s/76TUgsjaqb-H0Ep4iMwqmA',
                            'image' => config('app.base_url').'/img/Calendar.jpg',
                        ]
                    ),
                ];

                return new News($items);
                break;
            case 'xuyuanqiang':
                $items = [
                    new NewsItem(
                        [
                            'title' => '许愿墙',
                            'description' => '这里是资讯民大许愿墙。\n你和小塔，只差一个心愿。',
                            'url' => 'http://wish.stuzone.com/',
                            'image' => 'http://ww1.sinaimg.cn/large/98d2e36bjw1eruqmaxcnwj20go099ad3.jpg',
                        ]
                    ),
                ];

                return new News($items);
                break;
            case 'putonghua':
                $items = [
                    new NewsItem(
                        [
                            'title' => '干货丨普通话等级考试报名攻略',
                            'description' => '小塔整理的普通话考试干货，内容有不完整的地方欢迎来补充哦~希望这个干货能帮到各位塔粉~',
                            'url' => 'https://mp.weixin.qq.com/s/wRz-ztt0OI9oKtKKubhIxw',
                            'image' => config('app.base_url').'/img/putonghua.jpg',
                        ]
                    ),
                ];

                return new News($items);
                break;
            case 'teacher':
                $items = [
                    new NewsItem(
                        [
                            'title' => '教师资格证考试建议',
                            'description' => '湖北地区是国家统考地区，同样属于统考地区的还有河北，山东，山西，贵州，浙江，海南，安徽，上海，广西。',
                            'url' => config('app.blog_url').'/teacher-certification.html',
                            'image' => config('app.base_url').'/img/teacher.jpg',
                        ]
                    ),
                ];

                return new News($items);
                break;
            case 'yibaobaoxiao':
                $items = [
                    new NewsItem(
                        [
                            'title' => '【干货】大学生医保报销流程及事宜 ',
                            'description' => "大学生医保的干货\n希望可以帮到塔粉们~\n有问题可以直接留言指出哦！",
                            'url' => 'https://mp.weixin.qq.com/s/8ZH5R2n4OkG9AIi0WsGo-A',
                            'image' => config('app.base_url').'/img/yibao.jpg',
                        ]
                    ),
                ];

                return new News($items);
                break;
            case 'minren':
                $content = '《民人志》往期目录：'.config('app.blog_url').'/category/minrenzhi';

                return $content;
                break;
            case 'xinli':
                $content = '《心理咨询》往期目录：'.config('app.blog_url').'/category/xinlizixun';

                return $content;
                break;
            case 'zhaopin':
                $content = '<a href="'.config('app.blog_url').'/tag/zhaopin">点此进入一周招聘</a>';

                return $content;
                break;
            case 'studyroom':
                $common = app('wechat_common');
                $openid = $common->openid;
                $content = '<a href="https://wechat.stuzone.com/iscuecer/lab_query/web/studyroom?openid='.
                    $openid.'">自习室查询</a>';

                return $content;
                break;
            case 'suggest':
                $MessageStr = array('谢谢你的反馈。么么哒/:,@-D', '你的反馈我们已经收到，谢谢你对我们的支持。么么哒/:hug', '你的意见我们已经收录，感谢你的支持。么么哒/::)');
                $MessageStrRandom = rand(0, 2);
                $content = $MessageStr[$MessageStrRandom];

                return $content;
                break;
            case 'mental_test':
                $content = '<a href="http://xinli.scuec.edu.cn/login.aspx">心理素质能力测试系统</a>';

                return $content;
                break;
            case 'campus_network':
                $items = [
                    new NewsItem(
                        [
                            'title' => '小塔的校园网使用说明书',
                            'description' => '校园网的秘密都在这里了~',
                            'url' => 'http://mp.weixin.qq.com/s?__biz=MzA5OTA0ODUyOA==&mid=400024069'.
                                '&idx=1&sn=58c58c242113fdeeb86ea575ac997d7d&scene=4#wechat_redirect',
                            'image' => config('app.base_url').'/img/wifi.jpg',
                        ]
                    ),
                    new NewsItem(
                        [
                            'title' => '【新技能】用路由器共享校园网',
                            'description' => '小塔教你如何在宿舍使用路由器共享校园网~',
                            'url' => 'http://mp.weixin.qq.com/s?__biz=MzA5OTA0ODUyOA==&mid=211654347&idx=1'.
                                '&sn=b44754a9f238824bbc2b117d0e915b33',
                        ]
                    ),
                ];

                return new News($items);
            case 'ruxue':
                $items = [
                    new NewsItem(
                        [
                            'title' => '中南民族大学2014年新生入学须知',
                            'url' => config('app.blog_url').'/2014-freshmen-notice.html',
                            'image' => 'http://ww1.sinaimg.cn/mw690/98d2e36bgw1ejckhujt7aj20ci08cdgv.jpg',
                        ]
                    ),
                    new NewsItem(
                        [
                            'title' => '中南民族大学2014年新生入学户口迁移须知',
                            'url' => config('app.blog_url').'/blog/2014-freshmen-account-migration.html',
                            'image' => 'http://ww4.sinaimg.cn/mw690/98d2e36bgw1ejckhuzx38j207y07xmxe.jpg',
                        ]
                    ),
                    new NewsItem(
                        [
                            'title' => '新生入学谨防盗抢诈骗提示',
                            'url' => config('app.blog_url').'/freshman-fangpian-warn.html',
                            'image' => 'http://ww4.sinaimg.cn/mw690/98d2e36bgw1ejckhuzx38j207y07xmxe.jpg',
                        ]
                    ),
                    new NewsItem(
                        [
                            'title' => '中南民族大学2014年新生学费、住宿费收费标准',
                            'url' => config('app.blog_url').'/2014-freshman-fees.html',
                            'image' => 'http://ww4.sinaimg.cn/mw690/98d2e36bgw1ejckhuzx38j207y07xmxe.jpg',
                        ]
                    ),
                ];

                return new News($items);
            case 'rebinding':
                $ssfwLink = HelperService::getBindingLink('ssfw');
                $content = '如需绑定教务系统/研究生管理系统账号请点击:'.$ssfwLink;
                $libLink = HelperService::getBindingLink('lib');
                $content = $content."\n".'如需绑定图书馆账号请点击:'.$libLink;
                $libLink = HelperService::getBindingLink('lab');
                $content = $content."\n".'如需绑定大学物理实验账号请点击:'.$libLink;

                return $content;
                break;
            case 'timetable':
                $account = new AccountInfoController();
                $content = $this->replyHandle($account, 'getTableMessage');

                return $content;
                break;
            case 'exam':
                $account = new AccountInfoController();
                $content = $this->replyHandle($account, 'getExamMessage');

                return $content;
                break;
            case 'score':
                $account = new AccountInfoController();
                $content = $this->replyHandle($account, 'getScoreMessage');

                return $content;
                break;
            case 'score_scratchoff':
                $account = new AccountInfoController();
                $content = $this->replyHandle($account, 'guaguale');

                return $content;
                break;
            case 'score_scratchoff_close':
                $account = new AccountInfoController();
                $content = $this->replyHandle($account, 'guaguale_close');

                return $content;
                break;
            case 'security_education_tiny_course':
                $content = '<a href="http://wb.mycourse.cn/svnweiban/">点此进入安全微课</a>';

                return $content;
                break;
            case 'hlepPush':
                $content = '<a href="'.config('app.blog_url').'/information-to-help-push-rules.html">帮推</a>';
                $content = '点击查看:'.$content;

                return $content;
                break;
            case 'fanfou':
                $content = '<a href="'.config('app.blog_url').'/tag/fanfou">点此进入饭否</a>';

                return $content;
                break;
            case 'jieyongbiao':
                $content = '<a href="http://pan.baidu.com/s/1o7PjHuq">借用表</a>';

                return $content;
                break;
            case 'about':
                $questionAndAnswer = '<a href="'.config('app.blog_url').'/about">资讯民大Q&A</a>';
                $content = '资讯民大是学生工作部(处)下属的学生资讯集团推出的为全校师生提供信息查询、权威资讯的微信公众平台。更多详情请点击'.$questionAndAnswer."\n©学生资讯集团\n当前版本: 2.0\n技术支持:".'<a href="http://126.am/bitworkshop">比特工场</a>'."\n".'<a href="'.sconfig('app.blog_url').'/contact-us">联系我们</a>'.' | <a href="'.config('app.blog_url').'/join">加入我们</a>';

                return $content;
                break;
            case 'weiquan':
                $content = '<a href="'.config('app.blog_url').'/tag/weiquan">点此进入维权</a>';

                return $content;
                break;
            case 'xinlizixun':
                $content = '<a href="'.config('app.blog_url').'/tag/%E5%BF%83%E7%90%86%E5%92%A8%E8%AF%A2">点此进入心理咨询</a>';

                return $content;
                break;
            case 'xuanke':
                $content = '<a href="http://xk.scuec.edu.cn/xsxk/login.xk">点击进入(内网)</a>';
                //$contentstr = '<a href="'.self::BLOG_URL.'/tag/xuanke">点此进入选课</a>';
                return $content;
                break;
            case 'tuition':
                $content = '<a href="'.config('app.blog_url').'/tag/tuition">点此进入学费</a>';

                return $content;
                break;
            case 'tel':
                $content = HelperService::getEmoji("\ue036")."️学校各部门主要电话\n"
                    .HelperService::getEmoji("\ue21c")."学校办公室（校办）\n"
                    ."电话：67841005 \n"
                    .HelperService::getEmoji("\ue21d")."学生工作部（处）\n"
                    ."电话：027-67843281 \n"
                    .HelperService::getEmoji("\ue21e")."财务处学费管理科\n"
                    ."电话：027-67844438\n"
                    .HelperService::getEmoji("\ue21f")."保卫处\n"
                    ."* 值班室：0776-87532535\n"
                    ."* 治安科：0776-67842250\n"
                    ."* 校园报警：0776-67843110\n"
                    .HelperService::getEmoji("\ue220")."后勤保障处\n"
                    ."电话：027-67842164\n"
                    .HelperService::getEmoji("\ue221")."民族学博物馆\n"
                    ."电话：027-67842750\n"
                    .HelperService::getEmoji("\ue222")."现代教育技术中心\n"
                    ."*网络服务：027-67843672\n"
                    ."*一卡通服务：027-67841627\n"
                    .HelperService::getEmoji("\ue223")."创新创业学院\n"
                    ."*办公室电话：67842548\n"
                    .HelperService::getEmoji("\ue224")."校医院\n"
                    ."24h急诊电话：15802793857\n"
                    .HelperService::getEmoji("\ue21c").HelperService::getEmoji("\ue225")."铁箕山派出所\n"
                    ."电话：(027)87400110,(027)85391757\n"
                    .HelperService::getEmoji("\ue21c").HelperService::getEmoji("\ue21c")."校友办\n"
                    ."电话：027-67841925 \n"
                    .HelperService::getEmoji("\ue21c").HelperService::getEmoji("\ue21d")."招生就业工作处\n"
                    ."*招生办公室027-67842763\n"
                    ."*就业指导服务中心027-67842082\n"
                    .HelperService::getEmoji("\ue21c").HelperService::getEmoji("\ue21e")."后勤保障处\n"
                    ."*物业中心报修电话\n"
                    ."北区宿舍：67842513\n"
                    ."南区宿舍：67843692\n"
                    ."*热水\n"
                    ."北区：87055129（24h）\n"
                    ."南区：15972179137（24h）\n"
                    ."*开水洗衣机直饮水\n"
                    ."北区：13517242577（24h）\n"
                    ."南区：13517242577（24h）\n"
                    .HelperService::getEmoji("\ue21c").HelperService::getEmoji("\ue21f")."图书馆\n"
                    .'电话：64840979';

                return $content;
                break;
            case 'grow_survey':
                $content = '<a href="https://www.wenjuan.com/s/qIRjyy/">成长背景调查</a>';

                return $content;
                break;
            case 'tazaihushuo':
                $content = '<a href="http://mp.weixin.qq.com/mp/homepage?__biz=MzA5OTA0ODUyOA==&hid=2&sn=a2675d3d40e662e2564824a8e9c98d02#wechat_redirect">塔在湖说</a>';

                return $content;
                break;
            case 'cet_id_collector':
                return '十分抱歉，该功能还在测试中~';
                break;
            case 'cet_query':
                // 2018-2-27 changed by itsl
                // $contentstr =  '<a href="https://weixiao.qq.com/apps/public/cet/index.html">四六级成绩查询入口</a>';
                $content = '<a href="http://cet.neea.edu.cn/cet/">四六级成绩查询入口</a>';

                return $content;
                break;
            case 'cet_reply':
                $content = '回复关键词"四六级准考证"储存或查询四六级准考证号，回复关键词"四六级成绩"进入四六级成绩查询入口哦~';

                return $content;
                break;
            case 'i_finished':
                $content = '小塔已经收到你的毕业打卡内容啦~感谢你的参与，打卡结果小塔会再回复你哒/:heart希望你能坚持打卡哦!';

                return $content;
                break;
            case 'refresh_ttable':
                $content = '目前课表使用自动刷新机制，所以你不需要手动刷新课表啦～';

                return $content;
                break;
            case 'yuedutiaozhan':
                $url = '<a href="https://www.wjx.top/m/17556892.aspx">阅读挑战</a>';
                $content = '点击进入'.$url;

                return $content;

                break;
            case 'library':
                $bookName = $this->get_content($keyword, '图书');   // 获取需要检索的图书的关键字
                if (strlen($bookName)) {    // 判断是否取得关键字，未取得设为空
                    //书目查询地址
                    $bookurl = 'http://coin.lib.scuec.edu.cn/opac/openlink.php?strSearchType=title&strText='.$bookName.'&doctype=ALL&location=ALL';
                    $content = '<a href="'.$bookurl.'">中南民族大学移动图书馆-'.$bookName.'</a>';
                    $content = '已为你检索到相关书目，点击查看详情:'.$content;
                } else {
                    $content = '输入括号里的关键字【图书+书籍名称】即可检索相关书目信息。如【图书c语言】。';
                }

                return $content;
                break;
            case 'putonghuascore':
                $content = '<a href="http://hubei.cltt.org/Web/Login/PSCP01001.aspx">普通话成绩查询入口</a>';

                return $content;
                break;
            case 'CCP':
                $content = "《中国共产党章程》\nhttp://news.xinhuanet.com/18cpcnc/2012-11/18/c_113714762.htm";

                return $content;
                break;
            case 'ComputerBand2':
                $content = '<a href="http://cjcx.neea.edu.cn/ncre/query.html">计算机二级</a>';

                return $content;
                break;
            case 'tvlive':
                $content = '<a href="http://live.scuec.edu.cn/wall.html">电视直播</a>';

                return $content;
                break;
            case 'articlesearch':
                $content = '<a href="https://data.newrank.cn/m/s.html?s=OS0tOjQ2KCo4">号内搜</a>';

                return $content;
                break;
            case 'mindabizhi':
                $content = '<a href="http://mp.weixin.qq.com/mp/homepage?__biz=MzA5OTA0ODUyOA==&hid=3&sn=07d75dec2abb0f6f3315cf4819eaecf8#wechat_redirect">民大壁纸</a>';

                return $content;
                break;
            case 'panorama':
                $content = '<a href="http://720yun.com/t/aoe5jgq7xrflj9oxuj">民大全景图</a>';

                return $content;
                break;
            case 'suggestDes':
                $url = '<a href="'.config('app.blog_url').'/suggestion">〖在线留言〗</a>';
                $content = '感谢您对资讯民大的支持，你可以@小塔并提出您的意见或建议，我们收到后会及时回复你的。或者您可以'.$url;

                return $content;
                break;
            case 'xinshengcup':
                $content = "新生杯系列赛事赛程表\n乒乓球\n10月19日 早上8:00到12:00点 下午2:00到4:00点，体育馆。\n排球\n10月25日 早上8点到下午6点，排球场（游泳馆前）\n羽毛球\n10月18日 早上9：00正式开始（待定），初赛时间9：15—12：15，体育馆内。\n篮球\n10月16日 八强，19日决赛，体育馆旁边的篮球场。\n足球\n10月18—10月23日 ，南区操场。";

                return $content;
                break;
            case 'news':
                //关键字设为空，直接匹配最新资讯
                $keyword = '';

                $post_data = 'iyz=yz59461&keyword='.$keyword;   //参数
                // 1. 初始化
                $ch = curl_init();
                // 2. 设置选项
                curl_setopt($ch, CURLOPT_URL, 'http://www.stuzone.com/zone_info/weixin.php');   //请求的地址
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_POST, 1);      //POST方法
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                // 3. 执行并获取HTML文档内容
                $newsjson = curl_exec($ch);
                // 4. 释放curl句柄
                curl_close($ch);

                if ($newsjson) {      //返回的数据不是FALSE则说明匹配到了相关信息
                    $newsarray = json_decode($newsjson, true);   //json数据转化为数组
                    $newsnum = count($newsarray);   //统计数组的个数，即可得获得了多少条信息

                    for ($i = 0; $i < $newsnum; ++$i) {
                        $news[$i] = new NewsItem(
                            [
                                'title' => $newsarray[$i]['title'],   //标题
//                                'description' => $newsarray[$i]['description'], //描述，多图文不需要此属性
                                'url' => $newsarray[$i]['url'],   //网页地址
                                'image' => $newsarray[$i]['img'],    //图片链接
                            ]
                        );
                    }

                    return new News($news);
                } else {
                    $content = '额。没有获取到资讯。/:P-( 你可以稍后再试或者把问题反馈给我们。';

                    return $content;
                }
                break;
            case 'new_market':
                $items = [
                    new NewsItem(
                        [
                            'title' => '点击进入学生市场',
                            'description' => '',
                            'url' => 'https://ng.bitworkshop.net/login/',
                            'image' => config('app.base_url').'/img/new_market.jpg',
                        ]
                    ),
                ];

                return new News($items);
                break;
            case 'cxcyxf':
                $items = [
                    new NewsItem(
                        [
                            'title' => '干货 | 创新创业学分怎么算？看这里就搞定！',
                            'description' => '小塔精心整理的创新创业学分介绍，希望能帮助到需要的塔粉啦~',
                            'url' => 'http://mp.weixin.qq.com/s/2oGDgGpPwXCuCx6C00awrg',
                            'image' => config('app.base_url').'/img/cxcyxf.jpg',
                        ]
                    ),
                ];

                return new News($items);
                break;
            case 'jiemudan':
                $items = [
                    new NewsItem(
                        [
                            'title' => '学工部学生工作团队元旦晚会节目单',
                            'url' => config('app.base_url').'/img/jiemudan1.jpg',
                            'image' => config('app.base_url').'/img/jiemudanfengmian.jpg',
                        ]
                    ),
                ];

                return new News($items);
                break;
            case 'historyNews':
                $items = [
                    new NewsItem(
                        [
                            'title' => '资讯民大小站',
                            'url' => config('app.blog_url'),
                            'image' => config('app.base_url').'/img/zixunmindablog.jpg',
                        ]
                    ),
                    new NewsItem(
                        [
                            'title' => '历史消息',
                            'url' => 'https://dwz.cn/6kOyiztf',
                            'image' => config('app.base_url').'/img/historynews.jpg',
                        ]
                    ),
                ];

                return new News($items);
                break;
            case 'campus_card':
                $items = [
                    new NewsItem(
                        [
                            'title' => '一卡通充值(仅限校内访问)',
                            'description' => '你当前只能在校园网内访问一卡通充值的网站哦。',
                            'url' => 'http://xysf.scuec.edu.cn/',
//                            'image' => "http://www.stuzone.com/zixunminda/static/new_market.jpg",
                        ]
                    ),
                ];

                return new News($items);
                break;
            case 'borrow_renew':
                //TODO:
                break;
            case 'kaozheng':
                $items = [
                    new NewsItem(
                        [
                            'title' => '2018考试时间汇总，在大学多考几个证！',
                            'description' => '',
                            'url' => 'http://mp.weixin.qq.com/s/TSUGn5HmHJ1XxUjCIgsMoA',
                            'image' => config('app.base_url').'/img/kaozheng.jpg',
                        ]
                    ),
                ];

                return new News($items);
                break;
            case 'xuyuanqiang':
                $items = [
                    new NewsItem(
                        [
                            'title' => '许愿墙',
                            'description' => "这里是资讯民大许愿墙。\n你和小塔，只差一个心愿。",
                            'url' => 'http://wish.stuzone.com/',
                            'image' => 'http://ww1.sinaimg.cn/large/98d2e36bjw1eruqmaxcnwj20go099ad3.jpg',
                        ]
                    ),
                ];

                return new News($items);
                break;
            case 'borrow':
                $account = new LibInfoController();
                $content = $this->replyHandle($account, 'getMessage');

                return $content;
                break;
            case 'moneyInfo':
                $account = new AccountInfoController();
                $content = $this->replyHandle($account, 'getMoneyMessage');

                return $content;
                break;
            case 'signtest':
                $account = new WakeSignDetailInfosController();
                $content = $this->replyHandle($account, 'store');

                return $content;
                break;
            case 'sign':
                $account = new WakeSignDetailInfosController();
                $content = $this->replyHandle($account, 'store');

                return $content;
                break;
            case 'call_monkey':
                $hint = HelperService::randStr(8);
                $content = "程序猿已经把你的反馈记下来啦~程序猿有空时会回复你的。这是程序猿才能读懂的暗号：{$hint}";

                Common::writeCallLog($message['Content'].' hint：'.$hint);

                return $content;
                break;
            case 'test2':
                $items = [
                    new NewsItem(
                        [
                            'title' => '收到你的图片了噢',
                            'description' => '我们会尽快处理',
                            'url' => 'www.baidu.com',
                        ]
                    ),
                ];

                return new News($items);
            default:
                // 调用智能机器人
                $common = app('wechat_common');
                $openid = $common->openid;
                $apiurl = config('app.base_url').'/iBotCloud/iBotCloud.php?key=9dzRSIQj&question='.$keyword.'&userId='.$openid;
                $answerJson = file_get_contents($apiurl);
                $answerArray = json_decode($answerJson, true);
                if ('200' == $answerArray['status']) {
                    $answerStr = trim($answerArray['answerStr']);
                } elseif ('201' == $answerArray['status']) {
                    $answerArray = $answerArray['answerStr'];
                    $num = count($answerArray);
                    if ($num > 1) {
                        for ($i = 0,$answerStr = ''; $i < $num; ++$i) {
                            $answerStr = $answerStr.($i + 1).'-'.'<a href="'.$answerArray[$i]['url'].'">'.$answerArray[$i]['title'].'</a>';
                            if ($i < $num - 1) {
                                $answerStr .= "\n";
                            }
                        }
                    } else {
                        $answerStr = '<a href="'.$answerArray[0]['url'].'">'.$answerArray[0]['title'].'</a>';
                    }

                    $answerStr = "点击链接查看详情: \n".$answerStr;
                } elseif ('401' == $answerArray['status']) {
                    //$answerStr = "人类真是太强大了，小塔已经被你问倒了 /:,@@ 休眠中，下个月复活。";
                    $answerStr = '程序猿100块钱都不给我，罢工了。（￣へ￣）';
                }
                $strArr = array('回复【帮助】每个功能都很赞。', '如果有事找小塔，可以通过@小塔找到我~');
                $randNum = rand(0, 1);
                $contentstr = $answerStr."\n——这是小塔的智能回复。".$strArr[$randNum];

                return $contentstr;
        }
    }

    /**
     * 辅助函数.
     *
     * @param $keyword
     *
     * @return string
     */
    private function dealStr($keyword) //字符串处理，用于确定用户的目的，正则匹配增加容错率
    {
        if (preg_match('/^#|^＃/', $keyword)) {  // 注意此处#号的两种情况
            return 'chat';
        }
        if (preg_match("/@\s?小塔/", $keyword)) {
            return 'at_me';
        } elseif ('一周招聘' == $keyword) {
            return 'zhaopin';
        } elseif (('校园卡余额' == $keyword) || ('余额' == $keyword) || ('校园卡' == $keyword)) {
            return 'moneyInfo';
        } elseif ('校园卡消费详情' == $keyword) {
            return 'moneyInfoDetail';
        } elseif ('借用表' == $keyword) {
            return 'jieyongbiao';
        } elseif ('安全微课' == $keyword || '微课' == $keyword) {
            return 'security_education_tiny_course';
        } elseif ('饭否' == $keyword) {
            return 'fanfou';
        } elseif ('维权' == $keyword) {
            return 'weiquan';
        } elseif ('心理咨询' == $keyword) {
            return 'xinlizixun';
        } elseif ('选课' == $keyword) {
            return 'xuanke';
        } elseif ('学费' == $keyword) {
            return 'tuition';
        } elseif (preg_match('/@/', $keyword)) {
            if (strpos($keyword, '程序员') || strpos($keyword, '程序猿')) {
                return 'call_monkey';
            }

            return 'suggest';
        } elseif (preg_match('/^公交|^地铁/u', $keyword)) {
            return 'bus';
        } elseif (preg_match('/^火车/u', $keyword)) {
            return 'train';
        } elseif ('天气' == $keyword) {
            return 'weather';
        } elseif ('地图' == $keyword) {
            return 'map';
        } elseif (preg_match('/^快递/u', $keyword)) {
            return 'kuaidi';
        } elseif (('常用电话' == $keyword) or ('电话' == $keyword)) {
            return 'tel';
        } elseif (preg_match('/^翻译/u', $keyword)) {
            return 'translate';
        } elseif (preg_match('/^(成长背景)/', $keyword)) {
            return 'grow_survey';
        } elseif (preg_match('/^(塔在湖说|视频)/', $keyword)) {
            return 'tazaihushuo';
        } elseif (('心理素质能力测试' == $keyword) or ('心理素质测试' == $keyword) or ('心理测试' == $keyword) or ('心理测评' == $keyword)) {
            return 'mental_test';
        } elseif (preg_match('/^(四|六|四六)级准考证/', $keyword)) {
            return 'cet_id_collector'; //TODO:
        } elseif (preg_match('/^(四|六|四六)级成绩/', $keyword)) {
            return 'cet_query';
        } elseif (preg_match('/(四|六|四六)级/', $keyword)) {
            return 'cet_reply';
        } elseif (false !== strpos($keyword, '毕业打卡')
            || '毕业打卡' == $keyword) {
            return 'i_finished';
        } elseif (preg_match('/(打卡test)$/', $keyword)) {
            return 'signtest';
        } elseif (preg_match('/(打卡)$/', $keyword)) {
            return 'sign';
        } elseif (false !== strpos($keyword, '校园网')) {
            return 'campus_network';
        } elseif (('课表' == $keyword)) {
            return 'timetable';
        } elseif ('刷新课表' == $keyword) {
            return 'refresh_ttable';
        } elseif (('大学生医保' == $keyword) || '报销' == $keyword || '医保' == $keyword || '医疗报销' == $keyword) {
            return 'yibaobaoxiao';
        } elseif (('考试' == $keyword) || '查考试' == $keyword || '考试安排' == $keyword) {
            return 'exam';
        } elseif (('阅读挑战' == $keyword) || '反馈卡' == $keyword) {
            return 'yuedutiaozhan';
        } elseif ('关闭刮刮乐' == $keyword) {
            return 'score_scratchoff_close';
        } elseif ('刮刮乐' == $keyword || '成绩刮刮乐' == $keyword || '开启刮刮乐' == $keyword) {
            return 'score_scratchoff';
        } elseif ('成绩' == $keyword || '查成绩' == $keyword) {
            return 'score';
        } elseif ('时刻表' == $keyword || '校历' == $keyword || '时间表' == $keyword) {
            return 'SchoolCalendar';
        } elseif (
            '一卡通' == $keyword ||
            '一卡通充值' == $keyword ||
            '校园卡充值' == $keyword
        ) {
            return 'campus_card';
        } elseif (false !== strpos($keyword, '二手') ||
            false !== strpos($keyword, '闲置') ||
            false !== strpos($keyword, '跳蚤') ||
            false !== strpos($keyword, '学生市场')
        ) {
            return 'new_market';
        } elseif (('重新绑定' == $keyword) or ('绑定账号' == $keyword) or ('账号绑定' == $keyword)) {
            return 'rebinding';
        } elseif (('借阅' == $keyword) || ('当前借阅' == $keyword) || ('借阅查询' == $keyword)) {
            return 'borrow';
        } elseif (0 === strpos($keyword, '续借')) {
            return 'borrow_renew';
        } elseif (preg_match('/^图书/u', $keyword)) {
            return 'library';
        }
        if (('0' == $keyword) || ('帮助' == $keyword) || ('使用帮助' == $keyword)) { //此处有陷阱，如果字符串以合法的数字开头，就用该数字作为其值，否则其值为数字0。
            return 'help';
        } elseif (('辅修' == $keyword) or ('双学位' == $keyword)) {
            return 'fuxiu';
        } elseif (('奖学金' == $keyword) or ('助学金' == $keyword)) {
            return 'zhuxue';
        } elseif (false !== strpos($keyword, '许愿') || '心愿墙' == $keyword || '表白墙' == $keyword) {
            return 'xuyuanqiang';
        }
//        elseif (('普通话' == $keyword) or (false !== strpos($keyword, '普通话考试'))) {
//            return 'putonghua';
//        }
        elseif ('普通话成绩' == $keyword) {
            return 'putonghuascore';
        } elseif (('教师证' == $keyword) or (false !== strpos($keyword, '教师资格证'))) {
            return 'teacher';
        } elseif (('民人志' == $keyword) or ('名人志' == $keyword)) {
            return 'minren';
        } elseif ('心理咨询' == $keyword) {
            return 'xinli';
        } elseif (('自习室' == $keyword) or ('自习' == $keyword)) {
            return 'studyroom';
        } elseif ('资讯' == $keyword) {
            return 'news';
        } elseif ('节目单' == $keyword) {
            return 'jiemudan';
        } elseif ('党章' == $keyword) {
            return 'CCP';
        } elseif (false !== strpos($keyword, '许愿') || '心愿墙' == $keyword || '表白墙' == $keyword) {
            return 'xuyuanqiang';
        } elseif (false !== strpos($keyword, '新生杯')) {
            return 'xinshengcup';
        } elseif ('历史消息' == $keyword) {
            return 'historyNews';
        } elseif ('关于' == $keyword) {
            return 'about';
        } elseif ('帮推' == $keyword) {
            return 'hlepPush';
        } elseif ('计算机' == $keyword || '计算机二级' == $keyword) {
            return 'ComputerBand2';
        } elseif ('失物招领' == $keyword || '皮卡求' == $keyword) {
            //TODO:
            return 'picajiu';
        } elseif ('电视' == $keyword || '直播' == $keyword || '电视直播' == $keyword) {
            return 'tvlive';
        } elseif ('号内搜' == $keyword) {
            return 'articlesearch';
        } elseif ('壁纸' == $keyword || '每月壁纸' == $keyword || '精美壁纸' == $keyword) {
            return 'mindabizhi';
        } elseif (preg_match('/反馈|意见|建议|问题/u', $keyword)) {
            return 'suggestDes';
        } elseif (
            false !== strpos($keyword, '大物实验') ||
            false !== strpos($keyword, '物理实验')
        ) {
            //TODO:
            return 'lab_query';
        } elseif (false !== strpos($keyword, '考证')) {
            return 'kaozheng';
        } elseif (false !== strpos($keyword, '全景图')) {
            // 全景图
            return 'panorama';
        } elseif (false !== strpos($keyword, '创业学分') ||
            false !== strpos($keyword, '创新学分')) {
            // 创新创业学分
            return 'cxcyxf';
        } else {
            return 'unknow';
        }
    }

    //帮助信息文本。注意：下面的文字顶格换行微信里面显示也是这样
    private function helpStr()
    {
        $helpStr = '资讯民大功能菜单，回复括号里的关键词，get√
生活查询 :
'.HelperService::getEmoji("\ue04A").'【天气】 '.HelperService::getEmoji("\ue112").'【快递】
'.HelperService::getEmoji("\ue009").'【电话】 '.HelperService::getEmoji("\ue201").'【地图】
'.HelperService::getEmoji("\ue159").'【公交/地铁】
'.HelperService::getEmoji("\ue00C").'【电视直播】
'.HelperService::getEmoji("\ue01F").'【火车】
学习查询 :
'.HelperService::getEmoji("\ue157").'【课表】 '.HelperService::getEmoji("\ue44C").'【校历】
'.HelperService::getEmoji("\ue02B").'【考试】 '.HelperService::getEmoji("\ue14E").'【成绩】
'.HelperService::getEmoji("\ue345").'【翻译】 '.HelperService::getEmoji("\ue114").'【图书】
'.HelperService::getEmoji("\ue148").'【当前借阅】'.HelperService::getEmoji("\ue157").'【大物实验】
'.HelperService::getEmoji("\ue301").'【时刻表】

信息资讯 :
'.HelperService::getEmoji("\ue534").'【辅修】'.HelperService::getEmoji("\ue114").'【助学金】
'.HelperService::getEmoji("\ue302").'【教师证】'.HelperService::getEmoji("\ue157").'【医保】
'.HelperService::getEmoji("\ue114").'【号内搜】'.HelperService::getEmoji("\ue532").'【考证】

其它 :
'.HelperService::getEmoji("\ue428").'【微社区】
'.HelperService::getEmoji("\ue24e").'【关于】 '.HelperService::getEmoji("\ue327").'【帮推】
'.HelperService::getEmoji("\ue022").'【帮助】 '.HelperService::getEmoji("\ue103").'【反馈】
'.HelperService::getEmoji("\ue443").'【重新绑定】
'.HelperService::getEmoji("\ue019").'【历史消息】
更多功能努力研发ing';

        return $helpStr;
    }

    private function get_content($str, $keyword)    // 匹配字符串中关键词后面的内容
    {
        $pregStr = '/(?<='.$keyword.').*/u';    // 正则表达式语法，向后查找
        preg_match($pregStr, $str, $matches);   // 使用向后查找可以匹配例如“图书图书”的情况
        $content = trim($matches[0]);   // 去除前后空格
        // http://www.php.net/manual/zh/function.strpos.php
        if (false !== strpos($content, '+')) {  // 如果获得的字符串前面有+号则去除
            $content = preg_replace("/\+/", '', $content, 1);   // 去除加号，且只去除一次，解决用户多输入+号的情况
            $content = trim($content);
        }

        return $content;
    }

    private function replyHandle($className, $fnName, $params = array())
    {
        try {
            $content = call_user_func_array(array($className, $fnName), $params);
            if (is_array($content)) {
                return new News($content);
            } else {
                return $content;
            }
        } catch (\Exception $exception) {
            return "你好棒! 发现一个小bug. 快点@程序猿告诉他吧!";
            Common::writeLog($exception->getMessage().$exception->getTraceAsString());
        }
    }
}
