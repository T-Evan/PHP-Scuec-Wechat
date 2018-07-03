<?php
        $uppath = './img'; //文件上传路径
        //转换根目录的路径
        if (strpos($uppath, "/") == 0)
        {
         $i = 0;
         $thpath = $_SERVER["SCRIPT_NAME"];
         $thpath = substr($thpath, 1, strlen($thpath));
         while (strripos($thpath, "/") !== false)
         {
        $thpath = substr($thpath, strpos($thpath, "/") + 1, strlen($thpath));
        $i = ++$i;
         }
         $pp = "";
         for ($j = 0; $j < $i; ++$j)
         {
        $pp .= "../";
         }
         $uppaths = $pp . substr($uppath, 1, strlen($thpath));
        }
        $filename = 'bg';
        $f = $_FILES['pic'];
        if ($f['type'] != "image/png")
        {
         echo '只能上传png格式的文件';
         return false;
        }
        //获得文件扩展名
        $temp_arr = explode(".", $f["name"]);
        $file_ext = array_pop($temp_arr);
        $file_ext = trim($file_ext);
        $file_ext = strtolower($file_ext);
        //新文件名
        $new_file_name =$filename . '.' . $file_ext;
        echo ($dest = "$uppath/$new_file_name");
        echo (move_uploaded_file($f['tmp_name'], $dest));
?>
