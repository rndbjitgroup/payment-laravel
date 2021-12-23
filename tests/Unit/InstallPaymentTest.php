<?php 

namespace Bjit\Payment\Tests\Unit;

use Bjit\Payment\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class InstallPaymentTest extends TestCase
{

    /** @test */
    function the_install_command_copies_a_the_configuration()
    {
        if (File::exists(config_path('payments.php'))) {
            unlink(config_path('payments.php'));
        }

        $this->assertFalse(File::exists(config_path('payments.php')));

        Artisan::call('bjit-payment:install');

        $this->assertTrue(File::exists(config_path('payments.php')));
    }
}