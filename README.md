<h1>
Laravel Kernel Cadence
<a href="https://packagist.org/packages/faerber/laravel-kernel-cadence"><img src="https://img.shields.io/packagist/v/faerber/laravel-kernel-cadence" /></a>
<a href="https://github.com/benfaerber/laravel-kernel-cadence/actions"><img src="https://github.com/benfaerber/laravel-kernel-cadence/actions/workflows/php-ubuntu.yml/badge.svg" /></a>
</h1>

<p>
<a href="phpstan.neon"><img src="https://img.shields.io/badge/PHPStan-level%2010-brightgreen?logo=php" /></a>
<a href="LICENSE"><img src="https://img.shields.io/github/license/benfaerber/laravel-kernel-cadence?color=yellowgreen" /></a>
</p>

Give your every-N-minutes scheduled tasks an explicit phase, so they stop stacking on the top of the hour.

```php
// Before: every one of these fires at :00, :15, :30, :45
$schedule->command('tt:dailyOrders')->everyFifteenMinutes();
$schedule->command('tt:etsyNumbers')->everyFifteenMinutes();
$schedule->command('tt:tlmOrders')->everyFifteenMinutes();

// After: the same cadence, spread across the quarter hour
$schedule->command('tt:dailyOrders')->everyMinutes(15, offsetBy: 8);
$schedule->command('tt:etsyNumbers')->everyMinutes(15, offsetBy: 6);
$schedule->command('tt:tlmOrders')->everyMinutes(15, offsetBy: 10);
```

## The problem

Laravel's `everyFiveMinutes()`, `everyTenMinutes()`, `everyFifteenMinutes()` and friends all
phase-lock to minute 0. The hour-based helpers already take an offset — `hourlyAt(17)`,
`everySixHours(18)`, `everyTwoHours(25)` — but the minute-based ones do not, so the only way to
stagger sub-hourly work is to drop out of the fluent API into raw cron:

```php
// Offset 2 minutes to spread the per-tenant fan-out load;
// everyFiveMinutes() has no offset modifier.
$schedule->command('ail:dispatchDueOrderReminders')->cron('2-59/5 * * * *');
```

That matters because scheduled commands are usually thin dispatchers onto one queue, which fans
straight out to every worker and lands on the database at once. Reaching for the default helper
silently signs your task up for the :00 stampede.

[laravel/framework#59966](https://github.com/laravel/framework/pull/59966) proposed adding the
offset parameter upstream and was declined, with the suggestion to release it as a package. This is
that package, grown out of the `Cadence` class posted in that thread.

## Getting started

```
composer require faerber/laravel-kernel-cadence
```

The service provider is auto-discovered. There is nothing to publish and nothing to configure.

## Three ways to phase a task

Pick whichever reads best in your Kernel; they produce identical cron expressions.

**1. `everyMinutes()`, the offset parameter the framework helpers do not take:**

```php
$schedule->command('cron:shipRatings')->everyMinutes(10, offsetBy: 2)->withoutOverlapping(10);
```

**2. `offsetBy()`, re-phasing a framework helper you already wrote:**

```php
$schedule->command('cron:shipRatings')->everyTenMinutes()->offsetBy(2);
$schedule->command('cron:dailyReport')->dailyAt('03:00')->offsetBy(7);   //=> 03:07
$schedule->command('cron:warmCache')->hourly()->offsetBy(7);             //=> hourlyAt(7)
```

**3. `Cadence`, when you want the expression itself:**

```php
use Faerber\KernelCadence\Cadence;

Cadence::everyMinutes(15, offsetBy: 8)->expression();  //=> '8-59/15 * * * *'  (:08 :23 :38 :53)
Cadence::everyFifteenMinutes(8)->expression();         //=> the same thing, named
Cadence::everyFifteenMinutes()->offsetBy(8)->minutes(); //=> [8, 23, 38, 53]

$schedule->command('tt:dailyOrders')->cron(Cadence::everyFifteenMinutes(8)->expression());
```

A zero offset fires on exactly the same minutes as the helper it replaces, so switching a task over
is not a behaviour change until you pass an offset.

## Spreading a fan-out across lanes

When several related tasks share one cadence, let the package place them:

```php
use Faerber\KernelCadence\Cadence;

$lanes = Cadence::everyFifteenMinutes()->spreadAcross(3);  // phases :00, :05, :10

$schedule->command('cron:aquatreeOrderRawImport')->cadence($lanes->lane(0));
$schedule->command('cron:aquatreeOrderImport')->cadence($lanes->lane(1));
$schedule->command('cron:aquatreeUpdateShipping')->cadence($lanes->lane(2));
```

Lanes are guaranteed never to share a minute. When the lane count does not divide the interval the
phases round down, since cron cannot resolve finer than a minute.

## Guarding the whole schedule

Offsetting one task is easy to remember; keeping the whole Kernel spread out is not. `ScheduleLoad`
measures how many tasks land on each minute of the hour, so a test or a CI step can fail when one
minute gets overloaded.

### As an artisan command

```
php artisan schedule:load --max=12
```

```
  :00  11  ###########
  :01   3  ###
  :02   5  #####
  ...
  :59   2  ##

287 task firings per hour, 4.8 per minute on average, peaking at :00 (11 tasks).
No minute carries more than 12 tasks.
```

Exits non-zero when any minute exceeds `--max`, so it works as a CI check with no test to write.
By default only tasks that repeat every hour are counted, since a daily 03:00 report is not part of
the stampede the rest of the day; pass `--all` to count everything.

### As a test

```php
use Faerber\KernelCadence\Scheduling\ApplicationSchedule;
use Faerber\KernelCadence\Testing\AssertsScheduleLoad;
use Illuminate\Console\Scheduling\Schedule;

class ScheduleLoadDistributionTest extends TestCase {
    use AssertsScheduleLoad;

    private const MAX_TASKS_PER_MINUTE = 12;

    public function test_no_single_minute_carries_a_disproportionate_share(): void {
        $this->assertNoMinuteCarriesMoreThan(
            self::MAX_TASKS_PER_MINUTE,
            $this->hourlyRecurringLoad($this->schedule()),
        );
    }

    public function test_the_top_of_the_hour_is_not_an_outlier(): void {
        $this->assertTopOfTheHourIsNotAnOutlier($this->hourlyRecurringLoad($this->schedule()));
    }

    public function test_the_import_pipeline_stays_ordered(): void {
        $this->assertRunsBefore('cron:orderRawImport', 'cron:orderImport', $this->schedule());
    }

    private function schedule(): Schedule {
        return ApplicationSchedule::resolve($this->app);
    }
}
```

Use `ApplicationSchedule::resolve()` rather than resolving `Schedule` from the container yourself.
On Laravel 11 and 12 the schedule lives in a `withSchedule()` callback that only runs once artisan
starts, so a test that reads the container binding directly measures an **empty** schedule and
passes without checking anything. `ApplicationSchedule` starts artisan first, so both the Laravel 10
kernel style and the newer callback style are present. As a backstop, the budget assertions
themselves fail if the schedule they were handed carries no tasks at all.

A failure prints the minute, the count and the whole histogram, so you can see where the room is:

```
Minute :00 has 13 every-hour tasks scheduled (limit 12).
Give some of them an explicit offset with Cadence::everyMinutes($interval, offsetBy: $n).
  :00  13  #############
  :01   0
  ...
```

If your Kernel gates tasks behind an environment check or a kill switch, build the schedule the way
production builds it before asserting — the assertions take any `Schedule` or list of `Event`s.

### Directly

```php
use Faerber\KernelCadence\Analysis\ScheduleLoad;

$load = ScheduleLoad::ofHourlyRecurring($schedule);

$load->peak();            //=> MinuteLoad, ':00 (11 tasks)'
$load->at(15)->tasks;     //=> 4
$load->exceeding(12);     //=> list<MinuteLoad>, busiest first
$load->mean();            //=> 4.783333
$load->histogram();       //=> the bar chart above
```

## What is rejected, and why

Misconfiguration throws at definition time, during deploy, rather than silently never running.
Every exception extends `InvalidArgumentException`.

| Call | Result |
| --- | --- |
| `Cadence::everyMinutes(7)` | `InvalidInterval` — 7 does not divide 60, so the gap across the top of the hour would be uneven |
| `Cadence::everyMinutes(60)` | `InvalidInterval` — use `hourlyAt()` or `everySixHours()` for hour-based cadences |
| `Cadence::everyMinutes(15, offsetBy: 15)` | `InvalidOffset` — an offset at or beyond the interval is just a different phase of the same cadence |
| `->everyMinute()->offsetBy(1)` | `UnsupportedMinuteField` — a task running every minute has no phase |
| `->cron('55 * * * *')->offsetBy(7)` | `InvalidOffset` — :62 would move the task into the next hour |
| `->cron('0-30/5 * * * *')->offsetBy(2)` | `UnsupportedMinuteField` — that is a window, not a whole-hour cadence, and shifting it would change its meaning |
| `Cadence::everyFiveMinutes()->spreadAcross(6)` | `InvalidSpread` — more lanes than minutes would collide |

## Migrating from the standalone class

If you copied the `Cadence` class out of the pull request thread, the only change is that the
factories now return a `Cadence` rather than a string:

```php
// Before
$schedule->command('tt:dailyOrders')->cron(Cadence::everyMinutes(15, offsetBy: 8));
// After
$schedule->command('tt:dailyOrders')->everyMinutes(15, offsetBy: 8);
```

Every interval and offset rule is unchanged, including the exception messages.

## Compatibility

PHP 8.2+, Laravel 10, 11 and 12.

The `everyMinutes`, `offsetBy` and `cadence` macros are only registered if `Event` has no method of
that name. If a future Laravel ships its own, core wins and nothing here shadows it.

## Development

```
composer install
composer test        # phpunit
composer analyze     # phpstan, level 10
composer format      # php-cs-fixer
composer verify      # everything CI runs
```

## License

MIT. See [LICENSE](LICENSE).
