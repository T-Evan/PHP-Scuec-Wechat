<?php
require __DIR__.'/vendor/image-ocr/Image.php';
use My\ImageOCR\Image as Image;
use My\ImageOCR\StorageFile as StorageFile;

$db=new StorageFile();
//$a=$db->get();
//print_r($a);
if(isset($_POST['send'])&&$_POST['send']=="send"){
    $image=new Image("./img/inImgTemp.png");
    $code=$_POST['code'];
    $code_arr=str_split($code);
    for($i=0;$i<$image::CHAR_NUM;$i++){
        $hash_img_data=implode("",$image->splitImage($i));
        $db->add($code_arr[$i],$hash_img_data);
    }
    echo "<script>location.href='./study.php?t=".time()."'</script>";
}else{
    $image=new Image("http://coin.lib.scuec.edu.cn/reader/captcha.php");
    $image->draw(); //开启调试
    imagepng($image->_in_img,"./img/inImgTemp.png");
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
