<?php

namespace App\Http\Controllers;

use Config;
use EasyWeChat\Factory;
use Log;

class WeChatController extends Controller
{

    /**
     * 处理微信的请求消息
     *
     * @return string
     */
    public function serve()
    {
        $options = Config::get('wechat')['official_account']['default'];

        $app = Factory::officialAccount($options);

        $user = $app->user;

        $app->server->push(function ($message) use ($user) {
            return "Hello!";
        });

        return $app->server->serve();
    }
}
