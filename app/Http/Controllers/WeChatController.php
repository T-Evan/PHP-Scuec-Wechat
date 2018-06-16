<?php

namespace App\Http\Controllers;

use App\Http\MessageHandler\ImageMessageHandler;
use App\Http\MessageHandler\TextMessageHandler;
use App\Http\MessageHandler\UserInfoHandler;
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
        $app->server->push(UserInfoHandler::class); // 第一次发送消息时，保存用户openId
        $app->server->push(ImageMessageHandler::class, Message::IMAGE); // 处理图片消息
        $app->server->push(TextMessageHandler::class, Message::TEXT); // 处理文字消息

        return $app->server->serve();
    }
}
