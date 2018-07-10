<?php
require_once __DIR__."/../class/RedisConfig.php";
header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" name="viewport" content="user-scalable=no, initial-scale=1, maximum-scale=1, minimum-scale=1, width=device-width, height=device-height" />
    <META HTTP-EQUIV="Pragma" CONTENT="no-cache" />
	<META HTTP-EQUIV="Cache-Control" CONTENT="no-cache" />
	<META HTTP-EQUIV="Expires" CONTENT="0" />
</head>
<body>
<?php
if ($_GET['key'] == 'slkexoeef0') {
    $redis = new Zixunminda\Redis();
    $redis->select(1);
    ##DATE
    echo "<p>".date('Y-m-d H:i:s')."</p>";
    ##FUNCTION OPEN RATIO:
    $res = $redis->hGetAll("user:public:score:flag_likes");
    if ($res != false) {
        $open = 0;
        foreach ($res as $value) {
            if ($value == "1") {
                $open ++;
            }
        }
        $all = count($res);
        echo "## FUNCTION ENABLE RATIO<br />";
        echo $open."/".$all;
        echo "<br />";
        $rate = $open/$all;
        echo "RATE = ";
        printf("%2.3f%%", $rate*100);
    }
    
    if (isset($_GET['type']) && $_GET['type'] == 'all') {
        ##CACHED SCORE COUNT
        $cachedCount = (int)count($redis->keys('*score:cache'));
        $hit = (int)$redis->get('score:hit');
        $count = (int)$redis->get('score:count');
        if ($count != 0) {
            $hitRate = $hit / $count;
        } else {
            $hitRate = "[empty]";
        }
        if ($hitRate !== false) {
            echo "<br /><br />## CACHED SCORE COUNT<br />"
                ."COUNT = ".$cachedCount
                ."<br />HIT RATE = ";
            printf("%2.3f%%", $hitRate*100);
        }

        ##CACHED EXAM_INFO COUNT
        $cachedCount = count($redis->keys('*exam:cache'));
        $hit = (int)$redis->get('exam:hit');
        $count = (int)$redis->get('exam:count');
        if ($count != 0) {
            $hitRate = $hit / $count;
        } else {
            $hitRate = "[empty]";
        }
        if ($hitRate !== false) {
            echo "<br /><br />## CACHED EXAM_INFO COUNT<br />"
                ."COUNT = ".$cachedCount
                ."<br />HIT RATE = ";
            printf("%2.3f%%", $hitRate*100);
        }
    }
    unset($redis);
}
?>
</body>
</html>
