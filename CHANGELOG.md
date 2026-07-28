# Changelog

## 1.0.0

First release, grown out of the `Cadence` class posted on
[laravel/framework#59966](https://github.com/laravel/framework/pull/59966).

- `Cadence`, a sub-hourly cron cadence with an explicit phase offset, plus named constructors
  mirroring the seven every-N-minutes helpers the pull request would have changed.
- `CadenceSpread`, which divides one interval into evenly phased lanes that never share a minute.
- `everyMinutes()`, `offsetBy()` and `cadence()` macros on `Illuminate\Console\Scheduling\Event`.
- `ScheduleLoad`, a per-minute histogram of the schedule, and `MinuteLoad`.
- `AssertsScheduleLoad`, assertions holding a schedule to a per-minute task budget.
- `schedule:load --max=N`, the same budget as a CI check.
