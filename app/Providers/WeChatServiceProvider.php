<?php

namespace App\Providers;

use App\Models\Common;
use App\Models\CommonLog;
use EasyWeChat\Kernel\Log\LogManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use EasyWeChat\Factory;
use Config;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\BufferHandler;
use Monolog\Logger;

class WeChatServiceProvider extends ServiceProvider
{

    /**
     * 是否延时加载提供器。
     *
     * @var bool
     */
//    protected $defer = true;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * 注册服务提供器。
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('wechat', function ($app) {
            $options = Config::get('wechat')['official_account']['default'];
            return Factory::officialAccount($options);
        });

        $this->app->singleton('wechat_log', function ($app) {
            return new LogManager(app('wechat'));
        });

        $this->app->singleton('wechat_common', function ($app) {
            return new Common();
        });

        $this->app->singleton('common_log', function ($app) {
            $handler = new CommonLog("/app/storage/logs/laravel.log");
            $handler->setFormatter(
                new LineFormatter("[%datetime%]%level_name% %message% %context% %extra%\n", 'i:s', true, true)
            );
            $monolog = new Logger(config('logging.daily'));
            $monolog->pushHandler(new BufferHandler($handler));
            return $monolog;
        });
    }

    /**
     * 获取提供器提供的服务。
     *
     * @return array
     */
    public function provides()
    {
        return [Factory::class];
    }
}
