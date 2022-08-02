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

        if ($this->app->runningInConsole()) {

            $this->publishConfig();

            $this->publishMigrations();

            $this->commands([
                InstallPayment::class
            ]);

        }

        if ( ! File::exists(config_path('payments.php'))) {
            Artisan::call('bjit-payment:install');
        }
        
    } 

    private function registerResources()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'payments');
    } 

    private function publishConfig()
    {
        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('payments.php')
        ], 'config');
    }

    private function publishMigrations()
    {
        if (! class_exists('CreatePsPaymentsTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_payments_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()) . '_create_ps_payments_table.php'),
            ], 'migrations');
        }

        if (! class_exists('CreatePsRefundsTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_refunds_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()) . '_create_ps_refunds_table.php'),
            ], 'migrations');
        }

        if (! class_exists('CreatePsCustomersTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_customers_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()) . '_create_ps_customers_table.php'),
            ], 'migrations');
        }

        if (! class_exists('CreatePsCardsTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_cards_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()) . '_create_ps_cards_table.php'),
            ], 'migrations');
        }

        if (! class_exists('CreatePsProductsTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_products_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()) . '_create_ps_products_table.php'),
            ], 'migrations');
        }

        if (! class_exists('CreatePsPlansTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_plans_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()) . '_create_ps_plans_table.php'),
            ], 'migrations');
        }

        if (! class_exists('CreatePsSubscriptionsTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_subscriptions_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()) . '_create_ps_subscriptions_table.php'),
            ], 'migrations');
        }

    }

}