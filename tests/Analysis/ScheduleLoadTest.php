<?php

namespace Faerber\KernelCadence\Tests\Analysis;

use Faerber\KernelCadence\Analysis\ScheduleLoad;
use Faerber\KernelCadence\Cadence;
use Faerber\KernelCadence\CadenceSpread;
use Faerber\KernelCadence\MinuteInterval;
use PHPUnit\Framework\TestCase;

class ScheduleLoadTest extends TestCase {
    public function test_it_counts_every_firing_of_every_task(): void {
        $load = ScheduleLoad::fromExpressions(['*/15 * * * *', '*/15 * * * *']);

        $this->assertSame(2, $load->at(0)->tasks);
        $this->assertSame(2, $load->at(15)->tasks);
        $this->assertSame(0, $load->at(1)->tasks);
        $this->assertSame(8, $load->totalTasks());
    }

    public function test_unoffset_helpers_stack_on_the_top_of_the_hour(): void {
        $load = ScheduleLoad::fromExpressions([
            '*/5 * * * *',
            '*/15 * * * *',
            '*/30 * * * *',
            '0 * * * *',
        ]);

        $this->assertSame(0, $load->peak()->minute);
        $this->assertSame(4, $load->peak()->tasks);
    }

    public function test_offsetting_flattens_the_peak(): void {
        $load = ScheduleLoad::fromExpressions(
            CadenceSpread::of(MinuteInterval::of(15), 4)->expressions(),
        );

        $this->assertSame(1, $load->peak()->tasks);
        $this->assertSame(16, $load->totalTasks());
    }

    public function test_it_reports_the_minutes_over_a_limit_busiest_first(): void {
        $load = ScheduleLoad::fromExpressions([
            '0 * * * *',
            '0 * * * *',
            '0 * * * *',
            '30 * * * *',
            '30 * * * *',
            '45 * * * *',
        ]);

        $over = $load->exceeding(1);

        $this->assertCount(2, $over);
        $this->assertSame(0, $over[0]->minute);
        $this->assertSame(3, $over[0]->tasks);
        $this->assertSame(30, $over[1]->minute);
    }

    public function test_nothing_exceeds_a_limit_that_is_high_enough(): void {
        $this->assertSame([], ScheduleLoad::fromExpressions(['0 * * * *'])->exceeding(1));
    }

    public function test_an_empty_schedule_has_no_load(): void {
        $load = ScheduleLoad::fromExpressions([]);

        $this->assertSame(0, $load->totalTasks());
        $this->assertSame(0, $load->peak()->tasks);
        $this->assertSame(0.0, $load->mean());
    }

    public function test_the_mean_is_per_minute_of_the_hour(): void {
        $this->assertSame(4 / 60, ScheduleLoad::fromExpressions(['*/15 * * * *'])->mean());
    }

    public function test_the_histogram_draws_one_row_per_minute(): void {
        $rows = explode("\n", ScheduleLoad::fromExpressions(['0 * * * *', '0 * * * *'])->histogram());

        $this->assertCount(60, $rows);
        $this->assertSame('  :00   2  ##', $rows[0]);
        $this->assertSame('  :01   0  ', $rows[1]);
    }

    public function test_it_measures_a_cadence_built_by_this_package(): void {
        $load = ScheduleLoad::fromExpressions([
            Cadence::everyFifteenMinutes()->expression(),
            Cadence::everyFifteenMinutes(7)->expression(),
        ]);

        $this->assertSame(1, $load->peak()->tasks);
        $this->assertSame(8, $load->totalTasks());
    }

    public function test_a_minute_load_describes_itself(): void {
        $peak = ScheduleLoad::fromExpressions(['5 * * * *', '5 * * * *'])->peak();

        $this->assertSame(':05 (2 tasks)', (string) $peak);
        $this->assertTrue($peak->exceeds(1));
        $this->assertFalse($peak->exceeds(2));
    }
}
