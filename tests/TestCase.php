<?php 

namespace Bjit\Payment\Tests;

use Bjit\Payment\PaymentServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase 
{
    public function setUp(): void 
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            PaymentServiceProvider::class
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        
    }

}