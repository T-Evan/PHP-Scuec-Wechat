<?php
require 'Image.php';
use  APP\Vendor\ImageOCR as Image;

while (true) {
    $image = new Image\Image('http://ecard.scuec.edu.cn/getCheckpic.action', 'ecard');
    imagepng($image->_in_img, './img/inImgTemp.png');
    //$image=new \ImageOCR\Image("./img/inImgTemp.png");
    $a = $image->find();
    $code = implode('', $a);
    if (is_numeric($code[0]) && !is_numeric($code[1]) && is_numeric($code[2])) {
        echo "验证码：$code \n";
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Study</title>
</head>
<body>
<form action="" method="post">
    <img src="img/inImgTemp.png">
    <input type="text" name="code">
    <input name="send" type="submit" value="send" />
</form>
</body>
</html>
