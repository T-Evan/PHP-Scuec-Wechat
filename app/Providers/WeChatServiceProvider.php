<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use EasyWeChat\Factory;
use Config;

class WeChatServiceProvider extends ServiceProvider
{
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
     * Register the application services.
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
    }
}
