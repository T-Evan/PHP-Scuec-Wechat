<?php

/**
 * 智能机器人回复类
 *
 * @version 1.0
 * @since 2014.1
 * @author iyaozhen
 * @web http://iyaozhen.com/
 * @package http://cloud.xiaoi.com/
 */

class iBotCloud
{
    private	$app_key="JTTAQHnkbnC9";	// Key
    private	$app_secret="vIRfcGoJcrsxFnNDTeyZ";		// Secret

    /*
    * 获取许可
    */
    private function get_xAuth()
    {
        // 官方提供的签名算法
        $app_key = $this->app_key;
        $app_secret = $this->app_secret;
        $realm = "xiaoi.com";
        $method = "POST";
        $uri = "/robot/ask.do";
        // nonce为40位随机数
        $nonce = '';
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        for ( $i = 0; $i < 40; $i++) {
            $nonce .= $chars[ mt_rand(0, strlen($chars) - 1) ];
        }
        $HA1 = sha1($app_key . ":" . $realm . ":" . $app_secret);
        $HA2 = sha1($method . ":" . $uri);
        $sign = sha1($HA1 . ":" . $nonce . ":" . $HA2);		// signature的值

        $xAuth = "app_key=\"$app_key\", nonce=\"$nonce\", signature=\"$sign\"";		// 注意：三个值都需要带上引号

        return $xAuth;
    }

    /*
    * 得到回答
    * $question：问题
    * $userId：用户的id，便于不同用户采取不同的问答策略
    */
    public function get_answer($question, $userId)
    {
        $xAuth = $this->get_xAuth();
        // http头部信息，X-Auth为必须项，用作验证
        $header = array (
            "POST /robot/ask.do HTTP/1.1",
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "Host: nlp.xiaoi.com",
            "Connection: Keep-Alive",
            //	"Content-Length: XXX",	// 此参数不好计算，不设置即可
            "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
            "X-Auth: ".$xAuth,
            "X-Requested-With: XMLHttpRequest"
        );
        $postData = "question=\"".$question."\"&userId=".$userId."&platform=weixin&type=0";		// 注意：question的值需要带上引号

        // 1. 初始化
        $ch = curl_init();
        // 2. 设置选项
        curl_setopt($ch, CURLOPT_URL, "http://nlp.xiaoi.com/robot/ask.do");		// 请求的地址
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);	// 不自动返回内容
        curl_setopt($ch, CURLOPT_HEADER, 0);	// 不取得返回头信息
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);  // 设置http头部信息
        curl_setopt($ch, CURLOPT_POST, 1);		// POST方法
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);	// 连接响应时间
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);	// 整个curl执行时间
        // 3. 执行并获取HTML文档内容
        $answerStr = curl_exec($ch);
        // 4. 释放curl句柄
        curl_close($ch);

        return $answerStr;
    }
}

/*
* 实例化，调用
* 401: 机器人罢工
* 404：参数缺失
* 403：key认证错误
* 200：成功获取回复（普通文本）
* 201：成功获取回复（带有超链接）
*/

if (isset($_GET['key']) && isset($_GET['question']) && isset($_GET['userId'])) {
    if ($_GET['key'] == '9dzRSIQj') {
        $question = $_GET['question'];
        $userId = $_GET['userId'];

        $iBot = new iBotCloud();
        $answerStr = $iBot->get_answer($question, $userId);
        if(strpos($answerStr, "ERROR")){
            $contentstr = array('status' => '401', 'answerStr' => '');
        }
        else{
            if (preg_match("/link/", $answerStr)) {
                // 转换为html Dom
                $answerStr = str_replace('[', '<', $answerStr);
                $answerStr = str_replace(']', '>', $answerStr);
                $answerStr = str_replace('link', 'a', $answerStr);

                // php html Dom
                require("../simple_html_dom.php");
                $html = new simple_html_dom();
                $html -> load($answerStr);	// 加载字符串
                $a = $html->find('a');	// 找到a标签
                $forNum = (count($a) > 5) ? 5 : count($a);	// 循环次数
                for ($i = 0; $i < $forNum; $i++) {
                    $linkInfo[$i]['title'] = $a[$i]->plaintext;		// a标签内容即标题
                    $linkInfo[$i]['url'] = $a[$i]->url;		// 链接
                }
                $html->clear();

                $contentstr = array('status' => '201', 'answerStr' => $linkInfo);
            }
            else{
                $contentstr = array('status' => '200', 'answerStr' => $answerStr);
            }
        }
    }
    else{
        // key 认证错误
        $contentstr = array('status' => '403', 'answerStr' => '');
    }
}
else{
    // 缺少参数
    $contentstr = array('status' => '404', 'answerStr' => '');
}
//echo json_encode($contentstr, JSON_UNESCAPED_SLASHES);
echo json_encode($contentstr);

?>