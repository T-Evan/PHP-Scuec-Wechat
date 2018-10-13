<?php

namespace App\Providers;

use App\Http\Service\AccountService\AccountService;
use Illuminate\Support\ServiceProvider;

class AccountServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {

    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('Account', function () {
            return new AccountService();
        });
    }
}
