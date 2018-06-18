<?php
/**
 * Created by PhpStorm.
 * User: YiWan
 * Date: 2018/6/18
 * Time: 20:57
 * 识别图书馆验证码
 */
namespace App\Http\Service;

use APP\Vendor\ImageOCR\Image;

require __DIR__.'/../../Vendor/image-ocr/Image.php';

class ReadCaptcha
{
    private $content;
    private $cookie;
    private $captcha;

    public function __construct($c)
    {
        $this->content = $c;
    }

    public function showImg()
    {
        $img = $this->content;
        $file_content=chunk_split(base64_encode($img));//base64编码
        $img='data:image/'.'png'.';base64,'.$file_content;//合成图片的base64编码
        $image_ocr=new Image($img);
        $res=$image_ocr->find();
        $code=null;
        for ($i=0; $i<count($res); $i++) {
            $code=$code.$res[$i];
        }
        return $code;
    }
}
