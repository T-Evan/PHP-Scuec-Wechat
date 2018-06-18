<?php

namespace App\Providers;

use EasyWeChat\Kernel\Log\LogManager;
use Illuminate\Support\ServiceProvider;
use EasyWeChat\Factory;
use Config;

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
            $wechat_app = Factory::officialAccount($options);
            return $wechat_app;
        });

        $this->app->singleton('wechat_log', function ($app) {
            $log = new LogManager(app('wechat'));
            return $log;
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
