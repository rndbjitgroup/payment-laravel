<?php 

namespace Bjit\Payment\Console;

use Illuminate\Console\Command;

class InstallPayment extends Command
{

    protected $signature = 'bjit-payment:install';
    protected $description = 'Install the BJIT Payment';

    public function handle()
    {
        $this->info('Installing the Payment Package...');
        $this->info('Publishing configureation...');

        $this->call('vendor:publish', [
            '--provider' => "Bjit\Payment\PaymentServiceProvider",
            '--tag' => "config"
        ]);

        $this->call('vendor:publish', [
            '--provider' => "Bjit\Payment\PaymentServiceProvider",
            '--tag' => "migrations"
        ]);

        $this->info('Installed Payment Package');
    }

}