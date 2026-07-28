<?php

namespace Faerber\KernelCadence\Tests;

use Faerber\KernelCadence\Cadence;
use Faerber\KernelCadence\CadenceSpread;
use Faerber\KernelCadence\Exceptions\InvalidSpread;
use Faerber\KernelCadence\MinuteInterval;
use PHPUnit\Framework\TestCase;

class CadenceSpreadTest extends TestCase {
    public function test_it_divides_an_interval_into_evenly_phased_lanes(): void {
        $spread = CadenceSpread::of(MinuteInterval::of(15), 3);

        $this->assertSame([0, 5, 10], $spread->offsets());
        $this->assertSame([
            '*/15 * * * *',
            '5-59/15 * * * *',
            '10-59/15 * * * *',
        ], $spread->expressions());
    }

    public function test_it_rounds_down_when_lanes_do_not_divide_the_interval(): void {
        $this->assertSame([0, 7], CadenceSpread::of(MinuteInterval::of(15), 2)->offsets());
    }

    public function test_a_cadence_can_spread_itself(): void {
        $this->assertSame([0, 10, 20], Cadence::everyThirtyMinutes()->spreadAcross(3)->offsets());
    }

    public function test_the_phase_of_the_source_cadence_does_not_shift_the_lanes(): void {
        $this->assertSame([0, 5, 10], Cadence::everyFifteenMinutes(8)->spreadAcross(3)->offsets());
    }

    public function test_it_is_countable_and_iterable(): void {
        $spread = CadenceSpread::of(MinuteInterval::of(30), 3);

        $this->assertCount(3, $spread);
        $this->assertSame(10, $spread->lane(1)->offset()->minutes);

        foreach ($spread as $lane) {
            $this->assertSame(30, $lane->interval()->minutes);
        }
    }

    public function test_no_two_lanes_ever_share_a_minute(): void {
        foreach (CadenceSpread::of(MinuteInterval::of(20), 4) as $index => $lane) {
            $others = CadenceSpread::of(MinuteInterval::of(20), 4)->all();
            unset($others[$index]);

            foreach ($others as $other) {
                $this->assertSame([], array_intersect($lane->minutes(), $other->minutes()));
            }
        }
    }

    public function test_it_rejects_more_lanes_than_the_interval_has_minutes(): void {
        $this->expectException(InvalidSpread::class);
        $this->expectExceptionMessage('between 1 and 5 lanes, got 6');

        CadenceSpread::of(MinuteInterval::of(5), 6);
    }

    public function test_it_rejects_an_empty_spread(): void {
        $this->expectException(InvalidSpread::class);

        CadenceSpread::of(MinuteInterval::of(15), 0);
    }
}
