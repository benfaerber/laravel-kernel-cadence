<?php

namespace Faerber\KernelCadence\Tests\Cron;

use Faerber\KernelCadence\Cron\CronFields;
use Faerber\KernelCadence\Cron\EveryMinute;
use Faerber\KernelCadence\Cron\ExactMinutes;
use Faerber\KernelCadence\Cron\StepMinutes;
use Faerber\KernelCadence\Exceptions\InvalidOffset;
use Faerber\KernelCadence\Exceptions\UnsupportedMinuteField;
use Faerber\KernelCadence\MinuteOffset;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CronFieldsTest extends TestCase {
    #[DataProvider('laravelExpressions')]
    public function test_it_rephases_the_expressions_laravels_helpers_produce(
        string $expression,
        int $offset,
        string $expected,
    ): void {
        $this->assertSame(
            $expected,
            CronFields::parse($expression)->offsetBy(MinuteOffset::of($offset))->toExpression(),
        );
    }

    public static function laravelExpressions(): array {
        return [
            'everyFiveMinutes' => ['*/5 * * * *', 3, '3-59/5 * * * *'],
            'everyFifteenMinutes' => ['*/15 * * * *', 7, '7-59/15 * * * *'],
            'everyThirtyMinutes' => ['0,30 * * * *', 7, '7,37 * * * *'],
            'hourly' => ['0 * * * *', 7, '7 * * * *'],
            'daily' => ['0 0 * * *', 7, '7 0 * * *'],
            'weekly' => ['0 0 * * 0', 7, '7 0 * * 0'],
            'twiceDaily' => ['0 1,13 * * *', 7, '7 1,13 * * *'],
            'already phased' => ['4-59/15 * * * *', 7, '7-59/15 * * * *'],
            'zero offset is a no-op' => ['*/15 * * * *', 0, '*/15 * * * *'],
        ];
    }

    public function test_it_leaves_every_other_field_alone(): void {
        $this->assertSame(
            '7 3 1 6 2',
            CronFields::parse('0 3 1 6 2')->offsetBy(MinuteOffset::of(7))->toExpression(),
        );
    }

    #[DataProvider('minuteFields')]
    public function test_it_recognizes_the_shape_of_a_minute_field(string $expression, string $type): void {
        $this->assertInstanceOf($type, CronFields::parse($expression)->minuteField());
    }

    public static function minuteFields(): array {
        return [
            ['* * * * *', EveryMinute::class],
            ['*/15 * * * *', StepMinutes::class],
            ['8-59/15 * * * *', StepMinutes::class],
            ['0 * * * *', ExactMinutes::class],
            ['0,15,30,45 * * * *', ExactMinutes::class],
        ];
    }

    public function test_it_reports_whether_the_cadence_repeats_every_hour(): void {
        $this->assertTrue(CronFields::parse('*/15 * * * *')->repeatsEveryHour());
        $this->assertFalse(CronFields::parse('0 3 * * *')->repeatsEveryHour());
        $this->assertFalse(CronFields::parse('0 */6 * * *')->repeatsEveryHour());
    }

    public function test_a_schedule_that_runs_every_minute_has_no_phase(): void {
        $this->expectException(UnsupportedMinuteField::class);
        $this->expectExceptionMessage('no phase to offset');

        CronFields::parse('* * * * *')->offsetBy(MinuteOffset::of(7));
    }

    public function test_it_rejects_a_phase_at_or_beyond_the_interval(): void {
        $this->expectException(InvalidOffset::class);
        $this->expectExceptionMessage('between 0 and 15 minutes');

        CronFields::parse('*/15 * * * *')->offsetBy(MinuteOffset::of(15));
    }

    public function test_it_rejects_an_offset_that_would_spill_into_the_next_hour(): void {
        $this->expectException(InvalidOffset::class);
        $this->expectExceptionMessage('pushes past :59');

        CronFields::parse('55 * * * *')->offsetBy(MinuteOffset::of(7));
    }

    public function test_it_refuses_to_shift_a_window_that_is_not_a_whole_hour_cadence(): void {
        $this->expectException(UnsupportedMinuteField::class);
        $this->expectExceptionMessage("Cannot phase-shift the minute field '0-30/5'");

        CronFields::parse('0-30/5 * * * *')->offsetBy(MinuteOffset::of(2));
    }

    public function test_it_rejects_an_expression_that_is_not_five_fields(): void {
        $this->expectException(UnsupportedMinuteField::class);
        $this->expectExceptionMessage('must have 5 fields, got 3');

        CronFields::parse('*/15 * *');
    }

    public function test_it_tolerates_extra_whitespace(): void {
        $this->assertSame('*/15 * * * *', CronFields::parse("  */15   *  * * *\n")->toExpression());
    }
}
