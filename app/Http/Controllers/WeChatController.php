<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\AccountInfoController;
use App\Http\MessageHandler\ImageMessageHandler;
use App\Http\MessageHandler\OtherMessageHandler;
use App\Http\MessageHandler\TextMessageHandler;
use App\Http\MessageHandler\EventInfoHandler;
use App\Http\Service\TimeTableReplyService;
use Config;
use EasyWeChat\Factory;
use EasyWeChat\Kernel\Messages\Message;
use EasyWeChat\Kernel\Messages\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WeChatController extends Controller
{

    /**
     * 处理微信的请求消息
     *
     * @return string
     */
    public function serve()
    {
        $app =  app('wechat');
        $app->server->push(OtherMessageHandler::class); //优先过滤不能处理的消息类型，放在第一行防止错误覆盖其他正常信息处理结果
        $app->server->push(EventInfoHandler::class); //处理微信事件
        $app->server->push(TextMessageHandler::class, Message::TEXT); // 处理文字消息
        $app->server->push(ImageMessageHandler::class, Message::IMAGE); // 处理图片消息
        return $app->server->serve();
    }

    /**
     * 用于修复微信对于返回的图文链接加上subscence参数导致图文消息内容无法访问的问题
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\RedirectResponse
     *          |\Illuminate\Routing\Redirector|\Symfony\Component\HttpFoundation\Response
     */
    public function redirectWechatArticle(Request $request)
    {
        if (!$request->has('url')) {
            return response("参数错误")->setStatusCode(403);
        }
        $realUrl = urldecode($request->get('url'));
        $isValid = preg_match("/(https|http):\/\/.*\.weixin\.qq\.com\/.*/", $realUrl);
        if (!$isValid) {
            return response("参数错误")->setStatusCode(403);
        }
        return redirect($realUrl);
    }
}
