<?php

namespace Alexusmai\YandexMetrika;

use Illuminate\Support\ServiceProvider;

class YandexMetrikaServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $configPath = __DIR__.'/../config/yandex-metrika.php';

        $this->publishes([$configPath => config_path('yandex-metrika.php')],
            'yandex-metrika');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('yandexMetrika', function () {

            return new \Alexusmai\YandexMetrika\YandexMetrika;
        });
    }
}
