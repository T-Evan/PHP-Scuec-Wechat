<?php

namespace App\Http\Controllers;

use App\Http\MessageHandler\ImageMessageHandler;
use App\Http\MessageHandler\OtherMessageHandler;
use App\Http\MessageHandler\TextMessageHandler;
use App\Http\MessageHandler\EventInfoHandler;
use EasyWeChat\Kernel\Messages\Message;
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
}
