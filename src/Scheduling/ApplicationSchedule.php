<?php

namespace Faerber\KernelCadence\Scheduling;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Foundation\Application;

/**
 * The application's real schedule, populated the way artisan populates it.
 *
 * Resolving Schedule from the container is not enough on its own. Laravel 10
 * defines tasks in the console kernel's schedule() method, which the container
 * binding does run; Laravel 11 and 12 define them in a withSchedule() callback,
 * which only fires once the artisan console application starts. Outside of a
 * console command, only the first of those has happened, so a test that reads
 * the container binding directly can quietly measure an empty schedule and pass.
 *
 * This starts artisan first, so both styles are present either way.
 */
final readonly class ApplicationSchedule {
    public static function resolve(Application $app): Schedule {
        self::startArtisan($app);

        return $app->make(Schedule::class);
    }

    /**
     * Listing the commands builds the console application, which is what fires
     * the starting callbacks. It is the cheapest public way in; getArtisan() is
     * protected.
     */
    private static function startArtisan(Application $app): void {
        $app->make(ConsoleKernel::class)->all();
    }
}
