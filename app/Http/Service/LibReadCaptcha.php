<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/18
 * Time: 20:57
 * 识别图书馆验证码
 */

namespace App\Http\Service;

use App\Vendor\ImageOCR\Image;

class ReadCaptcha
{
    private $content;
    private $cookie;
    private $captcha;
    private $type;

    public function __construct($c, $type)
    {
        $this->content = $c;
        $this->type = $type;
    }

    /**
     * @return null|string
     * @throws \Exception
     */
    public function showImg()
    {
        $img = $this->content;
        $file_content = chunk_split(base64_encode($img)); //base64编码
        $img = 'data:image/'.'png'.';base64,'.$file_content; //合成图片的base64编码
        $image_ocr = new Image($img, $this->type);
        $res = $image_ocr->find();
        $code = null;
        for ($i = 0; $i < count($res); ++$i) {
            $code = $code.$res[$i];
        }

        return $code;
    }
}
