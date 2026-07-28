<?php

namespace Faerber\KernelCadence\Tests\Scheduling;

use Faerber\KernelCadence\Cadence;
use Faerber\KernelCadence\Exceptions\InvalidOffset;
use Faerber\KernelCadence\Exceptions\UnsupportedMinuteField;
use Faerber\KernelCadence\Tests\TestCase;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

class ScheduleMacrosTest extends TestCase {
    public function test_the_provider_registers_the_macros(): void {
        $this->assertTrue(Event::hasMacro('cadence'));
        $this->assertTrue(Event::hasMacro('everyMinutes'));
        $this->assertTrue(Event::hasMacro('offsetBy'));
    }

    public function test_every_minutes_takes_the_offset_the_framework_helpers_do_not(): void {
        $event = $this->command()->everyMinutes(15, offsetBy: 7);

        $this->assertSame('7-59/15 * * * *', $event->expression);
    }

    public function test_every_minutes_without_an_offset_matches_the_framework_helper(): void {
        $this->assertSame(
            $this->command()->everyFifteenMinutes()->expression,
            $this->command()->everyMinutes(15)->expression,
        );
    }

    public function test_offset_by_rephases_a_framework_helper_in_place(): void {
        $this->assertSame('7-59/15 * * * *', $this->command()->everyFifteenMinutes()->offsetBy(7)->expression);
        $this->assertSame('3-59/5 * * * *', $this->command()->everyFiveMinutes()->offsetBy(3)->expression);
        $this->assertSame('7-59/30 * * * *', $this->command()->everyThirtyMinutes()->offsetBy(7)->expression);
    }

    public function test_offset_by_turns_hourly_into_hourly_at(): void {
        $this->assertSame(
            $this->command()->hourlyAt(7)->expression,
            $this->command()->hourly()->offsetBy(7)->expression,
        );
    }

    public function test_offset_by_keeps_the_hour_and_day_of_a_daily_task(): void {
        $this->assertSame('7 3 * * *', $this->command()->dailyAt('03:00')->offsetBy(7)->expression);
    }

    public function test_a_cadence_can_be_applied_directly(): void {
        $event = $this->command()->cadence(Cadence::everyFifteenMinutes(7));

        $this->assertSame('7-59/15 * * * *', $event->expression);
    }

    public function test_the_macros_return_the_event_so_the_chain_continues(): void {
        $event = $this->command()->everyMinutes(15, 7)->withoutOverlapping(10);

        $this->assertSame('7-59/15 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_an_out_of_phase_offset_fails_at_definition_time(): void {
        $this->expectException(InvalidOffset::class);

        $this->command()->everyFifteenMinutes()->offsetBy(15);
    }

    public function test_offsetting_an_every_minute_task_fails_loudly(): void {
        $this->expectException(UnsupportedMinuteField::class);

        $this->command()->everyMinute()->offsetBy(1);
    }

    private function command(): Event {
        return (new Schedule())->command('cron:example');
    }
}
