<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/16
 * Time: 21:08
 */

namespace App\Http\MessageHandler;

use EasyWeChat\Kernel\Contracts\EventHandlerInterface;
use Illuminate\Support\Facades\Log;

class UserInfoHandler implements EventHandlerInterface
{
    public function handle($message = null)
    {
        $app = app('wechat');
        $user = $app->user->get($message['FromUserName']);
    }
}
