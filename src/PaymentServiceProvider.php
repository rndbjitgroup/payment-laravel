<?php 

namespace Bjit\Payment;

use Bjit\Payment\Console\InstallPayment;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Bjit\Payment\Contracts\Factory;
use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class PaymentServiceProvider extends ServiceProvider implements DeferrableProvider 
{

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    { 
        $this->registerResources();
        
        $this->app->singleton(Factory::class, function ($app) {
            return new PaymentManager($app);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [Factory::class];
    }

    public function boot()
    { 
        //dd('test boot');
        if ($this->app->runningInConsole()) {

            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('payments.php')
            ], 'config');

            $this->commands([
                InstallPayment::class
            ]);
        }

        Artisan::call('bjit-payment:install');
        
    } 

    private function registerResources()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'payments');
    }

}