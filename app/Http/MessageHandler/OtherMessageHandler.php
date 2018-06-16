<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/17
 * Time: 1:54
 */

namespace App\Http\MessageHandler;

use EasyWeChat\Kernel\Contracts\EventHandlerInterface;

class OtherMessageHandler implements EventHandlerInterface
{
    public function handle($message = null)
    {
        switch ($message['MsgType']) {
            default:
                $content = "你的".$message['MsgType']."信息已经收到，小塔看到后会回复你的。你还可以随便聊点别的。回复【帮助】获取功能菜单。";
                return $content;
                break;
        }
    }
}
