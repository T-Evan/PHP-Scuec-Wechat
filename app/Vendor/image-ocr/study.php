<?php
require 'Image.php';
use  APP\Vendor\ImageOCR as Image;
use  APP\Vendor\ImageOCR as StorageFile;

$db = new StorageFile\StorageFile();

//$a=$db->get();
//print_r($a);
if (isset($_POST['send']) && 'send' == $_POST['send']) {
    $image = new Image\Image('./img/inImgTemp.png', 'ecard');
    $code = $_POST['code'];
    $code_arr = str_split($code);
    for ($i = 0; $i < $image::CHAR_NUM; ++$i) {
//        $hash_img_data=implode("",$image->splitImage($i));
        $hash_img_data = $image->splitImage($i);
        $db->add($code_arr[$i], $hash_img_data);
    }
    echo "<script>location.href='./study.php?t=".time()."'</script>";
} else {
//    $image=new Image\Image("http://coin.lib.scuec.edu.cn/reader/captcha.php");
    $image = new Image\Image('http://ecard.scuec.edu.cn/getCheckpic.action');
    $image->draw(); //开启调试
    imagepng($image->_in_img, './img/inImgTemp.png');
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
