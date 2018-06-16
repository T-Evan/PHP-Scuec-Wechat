<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/16
 * Time: 19:47
 */

namespace App\Http\MessageHandler;

use EasyWeChat\Kernel\Contracts\EventHandlerInterface;
use EasyWeChat\Kernel\Messages\News;
use EasyWeChat\Kernel\Messages\NewsItem;
use Illuminate\Support\Facades\Log;

class ImageMessageHandler implements EventHandlerInterface
{
    public function handle($message = null)
    {
        $items = [
            new NewsItem(
                [
                'title'       => '收到你的图片了噢',
                'description' => '我们会尽快处理',
                'image'       => $message['PicUrl'],
                 ]
            ),
            new NewsItem(
                [
                    'title'       => '收到你的图片了',
                    'description' => '我们会尽快处理',
                    'image'       => $message['PicUrl'],
                ]
            ),
        ];
        return new News($items);
    }
}
