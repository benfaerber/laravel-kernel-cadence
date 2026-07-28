<?php

namespace Faerber\KernelCadence;

use Faerber\KernelCadence\Console\ScheduleLoadCommand;
use Faerber\KernelCadence\Scheduling\ScheduleMacros;
use Illuminate\Support\ServiceProvider;

final class CadenceServiceProvider extends ServiceProvider {
    public function register(): void {
        ScheduleMacros::register();
    }

    public function boot(): void {
        if ($this->app->runningInConsole()) {
            $this->commands([ScheduleLoadCommand::class]);
        }
    }
}
