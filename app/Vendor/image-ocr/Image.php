<?php

namespace App\Vendor\ImageOCR;

require 'StorageFile.php';

class Image
{
    //标准化的图像的宽高信息，可调
    const HASH_W = 40;
    const HASH_H = 40;

    //验证码图像字符串的个数
    protected $CHAR_NUM;

    //验证码类型（来源）
    protected $type;

    //图像的宽度与高度信息
    protected $_image_w;
    protected $_image_y;

    /**
     * 输入图像的句柄.
     *
     * @var resource
     */
    public $_in_img;

    /**
     * @var array 二值化的数组
     */
    protected $_hash_data;

    public function __construct($imgPath, $type)
    {
        $this->type = $type;
        switch ($this->type) {
            case 'library':
                $this->CHAR_NUM = 4;
                break;
            case 'ecard':
                $this->CHAR_NUM = 3;
                break;
        }

        //判断图像的类型
        $res = exif_imagetype($imgPath);

        switch ($res) {
            case 1:
                $this->_in_img = imagecreatefromgif($imgPath);
                break;
            case 2:
                $this->_in_img = imagecreatefromjpeg($imgPath);
                break;
            case 3:
                $this->_in_img = imagecreatefrompng($imgPath);
                break;
            case 6:
                $this->_in_img = imagecreatefromwbmp($imgPath);
                break;
            default:
                throw new \Exception('不支持的图片格式!');
                break;
        }

        //获取图像的大小信息
        $this->_image_w = getimagesize($imgPath)[0];
        $this->_image_h = getimagesize($imgPath)[1];

        $this->imageHash();
    }

    /**
     * 二值化，排除背景色，雪花等干扰项.
     *
     * @author mohuishou<1@lailin.xyz>
     */
    public function imageHash()
    {
        for ($i = 0; $i < $this->_image_h; ++$i) {
            for ($j = 0; $j < $this->_image_w; ++$j) {
                $rgb = imagecolorat($this->_in_img, $j, $i);
                $rgb_array = imagecolorsforindex($this->_in_img, $rgb);
                if ($rgb_array['red'] < 190 && $rgb_array['green'] < 190 && $rgb_array['blue'] < 190) {
                    $data[$i][$j] = 1;
                } else {
                    $data[$i][$j] = 0;
                }
            }
        }

        $data = $this->removeHotSpots($data);
        $data = $this->removeHotSpots($data);
        $data = $this->removeHotSpots($data);
        $this->_hash_data = $this->removeHotSpots($data);
        $this->removeZero();

        //$this->draw(); //开启调试
    }

    /**
     * 去除噪点.
     *
     * @author mohuishou<1@lailin.xyz>
     *
     * @param $hash_data
     *
     * @return mixed
     */
    public function removeHotSpots($hash_data)
    {
        if ('ecard' == $this->type) {
            //去除验证码两侧干扰线
            for ($i = 0; $i < $this->_image_h; ++$i) {
                for ($j = 40; $j < $this->_image_w; ++$j) {
                    $hash_data[$i][$j] = 0;
                }
            }
        }
        if ('library' == $this->type) {
            for ($i = 0; $i < $this->_image_h; ++$i) {
                for ($j = 0; $j < $this->_image_w; ++$j) {
                    if ($hash_data[$i][$j]) {
                        if ($this->isHotSpots($i, $j, $hash_data)) {
                            $hash_data[$i][$j] = 0;
                        }
                    }
                }
            }
            //去除验证码两侧干扰线
            for ($i = 0; $i < $this->_image_h; ++$i) {
                for ($j = 0; $j < 25; ++$j) {
                    $hash_data[$i][$j] = 0;
                }
            }
            for ($i = 0; $i < $this->_image_h; ++$i) {
                for ($j = $this->_image_w - 15; $j < $this->_image_w; ++$j) {
                    $hash_data[$i][$j] = 0;
                }
            }
        }

        return $hash_data;
    }

    /**
     * 判断是否是噪点.
     *
     * @author mohuishou<1@lailin.xyz>
     *
     * @param $i
     * @param $j
     * @param $hash_data
     *
     * @return bool ture:是噪点,false:不是
     */
    public function isHotSpots($i, $j, $hash_data)
    {
        if (0 == $i || 0 == $j || $i == ($this->_image_h - 1) || $j == ($this->_image_w - 1)) {
            return true;
        }
        //根据干扰线垂直像素点不超过四个，进行去除
        if (isset($hash_data[$i - 2][$j]) && isset($hash_data[$i + 2][$j]) && isset($hash_data[$i + 3][$j])) {
            if (!$hash_data[$i - 2][$j] && !$hash_data[$i + 2][$j] && !$hash_data[$i + 3][$j]) {
                return true;
            }
        }

        //待检查点为中心的九个点
        $points[0] = $hash_data[$i - 1][$j - 1];
        $points[1] = $hash_data[$i - 1][$j];
        $points[2] = $hash_data[$i - 1][$j + 1];
        $points[3] = $hash_data[$i][$j - 1];
        $points[4] = $hash_data[$i][$j]; //待检查点
        $points[5] = $hash_data[$i][$j + 1];
        $points[6] = $hash_data[$i + 1][$j - 1];
        $points[7] = $hash_data[$i + 1][$j];
        $points[8] = $hash_data[$i + 1][$j + 1];

        $count = 0;

        foreach ($points as $v) {
            if ($v) {
                ++$count;
            }
        }

        return $count < 4;
    }

    /**
     * 去除零行.
     *
     * @author mohuishou<1@lailin.xyz>
     *
     * @param null $data 当为默认值null时，自动赋值为&$this->_hash_data;
     *
     * @return array $data 去掉零行之后的二值化数组
     */
    public function removeZero($data = null)
    {
        null == $data && $data = &$this->_hash_data;
        foreach ($data as $k => $v) {
            if (0 == implode('', $v)) {
                unset($data[$k]);
            }
        }

        return $data;
    }

    /**
     * 用点阵的形式画出验证码图像，一般用于debug.
     *
     * @author mohuishou<1@lailin.xyz>
     *
     * @param null $data
     */
    public function draw($data = null)
    {
        null == $data && $data = $this->_hash_data;
        foreach ($data as $v) {
            foreach ($v as $val) {
                if ($val) {
                    echo '■';
                } else {
                    echo '□';
                }
            }
            echo "\n";
        }
    }

    /**
     * 分割图片，并将其标准化.
     *
     * @author mohuishou<1@lailin.xyz>
     *
     * @param int $n 图片的第几个字符
     *
     * @return array $hash_data 标准化之后的二值化图像字符串
     */
    public function splitImage($n)
    {
        $data = [];
        $a = $this->_image_w / $this->CHAR_NUM;
        if ('ecard' == $this->type) {
            if (0 == $n) {
                for ($i = $n * $a; $i < ($n + 1) * $a; ++$i) {
                    $column = array_column($this->_hash_data, $i);
                    if (0 != implode('', $column)) {
                        $data[] = $column;
                    }
                }
            } elseif (2 == $n) {
                for ($i = $n * $a - 10; $i < ($n + 1) * $a; ++$i) {
                    $column = array_column($this->_hash_data, $i);
                    if (0 != implode('', $column)) {
                        $data[] = $column;
                    }
                }
            } elseif (1 == $n) {
                for ($i = $n * $a - 10; $i < ($n + 1) * $a - 10; ++$i) {
                    $column = array_column($this->_hash_data, $i);
                    if (0 != implode('', $column)) {
                        $data[] = $column;
                    }
                }
            } else {
                for ($i = $n * $a + 10; $i < ($n + 1) * $a; ++$i) {
                    $column = array_column($this->_hash_data, $i);
                    if (0 != implode('', $column)) {
                        $data[] = $column;
                    }
                }
            }
        }
        if ('library' == $this->type) {
            if (0 == $n) {
                for ($i = $n * $a; $i < ($n + 1) * $a + 20; ++$i) {
                    $column = array_column($this->_hash_data, $i);
                    if (0 != implode('', $column)) {
                        $data[] = $column;
                    }
                }
            } elseif (3 == $n) {
                for ($i = $n * $a - 10; $i < ($n + 1) * $a + 20; ++$i) {
                    $column = array_column($this->_hash_data, $i);
                    if (0 != implode('', $column)) {
                        $data[] = $column;
                    }
                }
            } elseif (2 == $n) {
                for ($i = $n * $a; $i < ($n + 1) * $a - 10; ++$i) {
                    $column = array_column($this->_hash_data, $i);
                    if (0 != implode('', $column)) {
                        $data[] = $column;
                    }
                }
            } else {
                for ($i = $n * $a + 10; $i < ($n + 1) * $a; ++$i) {
                    $column = array_column($this->_hash_data, $i);
                    if (0 != implode('', $column)) {
                        $data[] = $column;
                    }
                }
            }
        }

//            if(array_count_values($column)[1]<100){
//                $data[]=$column;
//            }
//            print_r(array_count_values($column));

        $out_img_w = count($data) + 4;
        $out_img_h = count($data[0]) + 4;

        $out_img = imagecreatetruecolor($out_img_w, $out_img_h); //创建一幅真彩色图像
        $bg = imagecolorallocate($out_img, 255, 255, 255); //背景色画为白色
        imagefill($out_img, 0, 0, $bg);

        //一列一列的进行画图
        foreach ($data as $k => $v) {
            foreach ($v as $key => $val) {
                $color = 255;
                if ($val) {
                    $color = 0;
                }
                $c = imagecolorallocate($out_img, $color, $color, $color);
                imagesetpixel($out_img, $k + 2, $key + 2, $c);
            }
        }

        // imagepng($out_img,'./img/'.$n.'a'.time().'.png'); //保存每个分割字符用作debug
        //图像标准化
        $hash_img = $this->imageStandard($out_img, $out_img_w, $out_img_h);

        //图像二值化
        for ($i = 0; $i < self::HASH_H; ++$i) {
            for ($j = 0; $j < self::HASH_H; ++$j) {
                $rgb = imagecolorat($hash_img, $j, $i);
//                echo $rgb."\n";
                if ($rgb < 10000000) {
                    $hash_img_data[] = 1;
                } else {
                    $hash_img_data[] = 0;
                }
            }
        }
        //  imagepng($hash_img,'./img/test'.$n.time().'.png'); //保存每个标准化之后的分割字符用作debug
        return $hash_img_data;
    }

    /**
     * 图像标准化，将旋转的图像标准化.
     *
     * @author mohuishou<1@lailin.xyz>
     *
     * @param $img
     *
     * @return resource 标准的图像资源句柄
     */
    public function imageStandard($img)
    {
        $min_w = 999;
        $oimg = $img;
//        imagepng($img,'./img/1aa'.time().'.png');

        $c = imagecolorallocate($img, 255, 255, 255);
        for ($i = -30; $i < 30; ++$i) {
            $simg = imagerotate($img, $i, $c);
//            //计算字符宽度
            $simg_hash_data = $this->getWidth($simg);
            $w = count($simg_hash_data);
            if ($w < $min_w) {
                $oimg_hash_data = $simg_hash_data;
                $min_w = $w;
            }
        }

        $out_img_w = count($oimg_hash_data);
        $out_img_h = count($oimg_hash_data[0]);

        $out_img = imagecreatetruecolor($out_img_w, $out_img_h); //创建一幅真彩色图像
        $bg = imagecolorallocate($out_img, 255, 255, 255); //背景色画为白色
        imagefill($out_img, 0, 0, $bg);

        //一列一列的进行画图
        foreach ($oimg_hash_data as $k => $v) {
            foreach ($v as $key => $val) {
                $color = 255;
                if ($val) {
                    $color = 0;
                }
                $c = imagecolorallocate($out_img, $color, $color, $color);
                imagesetpixel($out_img, $k, $key, $c);
            }
        }
        //imagepng($out_img,'./img/'.time().'.png');

        $hash_img = imagecreatetruecolor(self::HASH_W, self::HASH_H);
        imagecopyresized($hash_img, $out_img, 0, 0, 0, 0, self::HASH_W, self::HASH_H, $out_img_w, $out_img_h);

        return $hash_img;
    }

    /**
     * 获取图像的宽度.
     *
     * @author mohuishou<1@lailin.xyz>
     *
     * @param $img 图像资源句柄
     *
     * @return int
     */
    public function getWidth($img)
    {
        //根据资源句柄获取整个图像的高与宽
        $img_w = imagesx($img);
        $img_h = imagesy($img);

        //图像二值化
        for ($i = 0; $i < $img_h; ++$i) {
            for ($j = 0; $j < $img_w; ++$j) {
                $rgb = imagecolorat($img, $j, $i);
                if (0 == $rgb) {
                    $data[$i][$j] = 1;
                } else {
                    $data[$i][$j] = 0;
                }
            }
        }

        //去掉零行
        $data = $this->removeZero($data);

        //按列取图像获取宽度
        for ($i = 0; $i < $img_w; ++$i) {
            $column = array_column($data, $i);
            if (0 != implode('', $column)) {
                $data1[] = $column;
            }
        }

        //返回
        return $data1;
    }

    /**
     * 识别字符串.
     *
     * @author mohuishou<1@lailin.xyz>
     *
     * @return array $res 识别成功的字符串数组
     */
    public function find()
    {
        $res = [];

        //从数据库中取出特征值
        $db = new StorageFile($this->type);
        $samples = $db->get();

        //分割字符串，并和特征值进行对比
        for ($i = 0; $i < $this->CHAR_NUM; ++$i) {
            $hash = $this->splitImage($i);
            $res[] = $this->compare($hash, $samples);
        }

        $retryNo = 0;
        if ('ecard' == $this->type) {
            while (true && $retryNo < 10) {
                if (is_numeric($res[0]) && !is_numeric($res[1]) && is_numeric($res[2])) {
                    break;
                }
                $res = [];
                //分割字符串，并和特征值进行对比 , ecard验证码识别率较低，重试50次尝试获取符合格式的验证码
                for ($i = 0; $i < $this->CHAR_NUM; ++$i) {
                    $hash = $this->splitImage($i);
                    $res[] = $this->compare($hash, $samples);
                }
                ++$retryNo;
            }
        }

        return $res;
    }

    /**
     * 和特征值库进行对比.
     *
     * @author mohuishou<1@lailin.xyz>
     *
     * @param array $hash    待识别的二值化图像字符串
     * @param array $samples 特征值库的数组
     *
     * @return string $code 返回识别的字符
     */
    public function compare($hash, $samples)
    {
        $s = 0;
        foreach ($samples as $k => $v) {
            foreach ($v as $val) {
                if ('library' == $this->type) {
                    $samples_hash_data = str_split($val);
                }
                if ('ecard' == $this->type) {
                    $samples_hash_data = ($val);
                }
                $c = count(array_intersect_assoc($samples_hash_data, $hash));

                if ($c > $s) {
                    $s = $c;
                    $code = $k;
                }
                if ($s > 0.9 * count($samples_hash_data)) {
//                    echo 0.8*count($samples_hash_data);
                    return $k;
                }
            }
        }

//        foreach($samples as $value) {
//            $samples_hash_data=str_split($value['hash_data']);
//            $c = count( array_intersect_assoc ($samples_hash_data, $hash) );
//            if($c>$s){
//                $s=$c;
//                $code=$value['code'];
//            }
//            if($s>0.8*count($samples_hash_data)){
//                echo 0.8*count($samples_hash_data);
//                return $value['code'];
//
//                break;
//            }
//        }
        return $code;
    }
}
