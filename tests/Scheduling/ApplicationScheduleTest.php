<?php

namespace Faerber\KernelCadence\Tests\Scheduling;

use Faerber\KernelCadence\Scheduling\ApplicationSchedule;
use Faerber\KernelCadence\Tests\TestCase;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Laravel 11 and 12 register the schedule with a withSchedule() callback, which
 * the framework wires up as a console application starting callback.
 */
class ApplicationScheduleTest extends TestCase {
    public function test_it_picks_up_tasks_registered_the_way_laravel_11_registers_them(): void {
        ConsoleApplication::starting(fn () => $this->app->make(Schedule::class)
            ->command('cron:late')
            ->everyMinutes(15, offsetBy: 7));

        $events = ApplicationSchedule::resolve($this->app)->events();

        $this->assertCount(1, $events);
        $this->assertSame('7-59/15 * * * *', $events[0]->expression);
    }

    public function test_resolving_the_container_binding_alone_would_have_missed_them(): void {
        ConsoleApplication::starting(fn () => $this->app->make(Schedule::class)->command('cron:late')->everyFifteenMinutes());

        $this->assertCount(0, $this->app->make(Schedule::class)->events());
        $this->assertCount(1, ApplicationSchedule::resolve($this->app)->events());
    }

    public function test_it_returns_the_same_schedule_the_container_holds(): void {
        $this->assertSame(
            ApplicationSchedule::resolve($this->app),
            $this->app->make(Schedule::class),
        );
    }
}
