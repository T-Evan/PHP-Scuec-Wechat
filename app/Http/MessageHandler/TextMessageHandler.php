<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/16
 * Time: 19:27
 */

namespace App\Http\MessageHandler;
use EasyWeChat\Kernel\Contracts\EventHandlerInterface;
use Illuminate\Support\Facades\Log;

class TextMessageHandler implements EventHandlerInterface
{
    public function handle($message = null){
        switch ($message['Content']){
            case 'test1':
                return 'test1√';
            case 'test2':
                return 'test2√';
            default:
                return $message['Content'].$message['FromUserName'];

        }
    }
}