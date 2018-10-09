<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Common extends Model
{
    public static $openid;

    public function __construct()
    {
        parent::__construct();
        $this->openid = $this->getOpenid();
    }

    /**
     * 获取openid.
     */
    public function getOpenid()
    {
        $message = app('wechat')->server->getMessage();
        return $message['FromUserName'] ?? null;

        //测试时开启，有空抽到新函数里
//        return 'onzftwySIXNVZolvsw_hUvvT8UN0';
    }


    /**
     * @param $message
     * @param string $sql
     * 记录错误日志
     */
    public static function writeLog($message, $sql = '')
    {
        $common = app('wechat_common');
        $openid = $common->openid;
        Log::useDailyFiles(storage_path('logs/error.log'));
        Log::error('openid：'.$openid.' error：'.$message);
    }

    /**
     * @param $message
     * @param string $sql
     * 记录反馈日志
     */
    public static function writeCallLog($message, $sql = '')
    {
        $common = app('wechat_common');
        $openid = $common->openid;
        Log::useDailyFiles(storage_path('logs/call.log'));
        Log::info('openid：'.$openid.' error：'.$message);
    }

    public static function getWrong()
    {
        return __CLASS__.'：'.__FUNCTION__.'：'.__LINE__;
    }
}
