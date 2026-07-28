<?php

namespace Faerber\KernelCadence\Tests;

use Faerber\KernelCadence\CadenceServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Testing\PendingCommand;
use Orchestra\Testbench\TestCase as Testbench;

abstract class TestCase extends Testbench {
    /** @return list<class-string> */
    protected function getPackageProviders($app): array {
        return [CadenceServiceProvider::class];
    }

    /** Testbench holds the application in a nullable property; a booted test has one. */
    protected function application(): Application {
        $app = $this->app;

        if ($app === null) {
            $this->fail('The application was not booted.');
        }

        return $app;
    }

    /** artisan() returns an exit code once the command has already run; ours has not. */
    protected function runArtisan(string $command): PendingCommand {
        $pending = $this->artisan($command);

        if (! $pending instanceof PendingCommand) {
            $this->fail("Expected a pending command for '{$command}'.");
        }

        return $pending;
    }
}
