<?php

namespace Faerber\KernelCadence\Tests;

use Faerber\KernelCadence\CadenceServiceProvider;
use Orchestra\Testbench\TestCase as Testbench;

abstract class TestCase extends Testbench {
    /** @return list<class-string> */
    protected function getPackageProviders($app): array {
        return [CadenceServiceProvider::class];
    }
}
