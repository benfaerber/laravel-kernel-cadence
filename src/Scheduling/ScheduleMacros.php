<?php

namespace Faerber\KernelCadence\Scheduling;

use Closure;
use Faerber\KernelCadence\Cadence;
use Faerber\KernelCadence\Cron\CronFields;
use Faerber\KernelCadence\MinuteOffset;
use Illuminate\Console\Scheduling\Event;

/**
 * Teaches the scheduler to speak in cadences.
 *
 *   $schedule->command('reports:generate')->everyMinutes(15, offsetBy: 7);
 *   $schedule->command('reports:generate')->everyFifteenMinutes()->offsetBy(7);
 *
 * Each macro is skipped if the framework ever ships a real method of that name,
 * since a macro only answers calls the class itself does not define. Deferring
 * keeps this package from quietly shadowing, or being shadowed by, core.
 */
final readonly class ScheduleMacros {
    public static function register(): void {
        self::define('cadence', self::cadence());
        self::define('everyMinutes', self::everyMinutes());
        self::define('offsetBy', self::offsetBy());
    }

    /**
     * Each macro body is built in a static context on purpose: a macro closure is
     * rebound to the Event it is called on, so $this must not already be bound.
     */
    private static function cadence(): Closure {
        return function (Cadence $cadence): Event {
            /** @var Event $this */
            return $this->cron($cadence->expression());
        };
    }

    /** The offset parameter the framework's every-N-minutes helpers do not take. */
    private static function everyMinutes(): Closure {
        return function (int $interval, int $offsetBy = 0): Event {
            /** @var Event $this */
            return $this->cron(Cadence::everyMinutes($interval, $offsetBy)->expression());
        };
    }

    /**
     * Re-phases the frequency already on the event, so the framework's own
     * helpers stay usable: everyFifteenMinutes()->offsetBy(7), hourly()->offsetBy(7).
     */
    private static function offsetBy(): Closure {
        return function (int $minutes): Event {
            /** @var Event $this */
            return $this->cron(
                CronFields::parse($this->expression)->offsetBy(MinuteOffset::of($minutes))->toExpression(),
            );
        };
    }

    private static function define(string $name, Closure $macro): void {
        if (method_exists(Event::class, $name) || Event::hasMacro($name)) {
            return;
        }

        Event::macro($name, $macro);
    }
}
