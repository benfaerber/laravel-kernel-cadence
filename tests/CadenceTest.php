<?php

namespace Faerber\KernelCadence\Tests;

use Cron\CronExpression;
use Faerber\KernelCadence\Cadence;
use Faerber\KernelCadence\Exceptions\InvalidInterval;
use Faerber\KernelCadence\Exceptions\InvalidOffset;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CadenceTest extends TestCase {
    public function test_a_zero_offset_matches_the_laravel_helper_it_replaces(): void {
        $this->assertSame('*/5 * * * *', Cadence::everyMinutes(5)->expression());

        $this->assertSame('*/15 * * * *', Cadence::everyMinutes(15)->expression());
    }

    public function test_an_offset_phases_the_cadence(): void {
        $this->assertSame('8-59/15 * * * *', Cadence::everyMinutes(15, offsetBy: 8)->expression());

        $this->assertSame('3-59/5 * * * *', Cadence::everyMinutes(5, offsetBy: 3)->expression());
    }

    public function test_it_casts_to_its_expression(): void {
        $this->assertSame('8-59/15 * * * *', (string) Cadence::everyMinutes(15, offsetBy: 8));
    }

    #[DataProvider('cadences')]
    public function test_it_fires_on_the_expected_minutes(int $interval, int $offset, array $expected): void {
        $cadence = Cadence::everyMinutes($interval, $offset);

        $this->assertSame($expected, $this->minutesFiring($cadence->expression()));

        $this->assertSame($expected, $cadence->minutes());
    }

    public static function cadences(): array {
        return [
            'every 15, offset 8' => [15, 8, [8, 23, 38, 53]],
            'every 15, offset 0' => [15, 0, [0, 15, 30, 45]],
            'every 30, offset 29' => [30, 29, [29, 59]],
            'every 10, offset 9' => [10, 9, [9, 19, 29, 39, 49, 59]],
            'every 20, offset 7' => [20, 7, [7, 27, 47]],
            'every 5, offset 1' => [5, 1, [1, 6, 11, 16, 21, 26, 31, 36, 41, 46, 51, 56]],
        ];
    }

    #[DataProvider('offsets')]
    public function test_every_offset_preserves_the_firing_count(int $offset): void {
        $cadence = Cadence::everyMinutes(15, $offset);

        $this->assertCount(4, $this->minutesFiring($cadence->expression()));

        $this->assertSame(4, $cadence->firesPerHour());
    }

    public static function offsets(): array {
        return array_map(fn (int $offset) => [$offset], range(0, 14));
    }

    #[DataProvider('namedCadences')]
    public function test_the_named_constructors_mirror_the_laravel_helpers(string $method, string $expected): void {
        $this->assertSame($expected, Cadence::{$method}(1)->expression());
    }

    public static function namedCadences(): array {
        return [
            ['everyTwoMinutes', '1-59/2 * * * *'],
            ['everyThreeMinutes', '1-59/3 * * * *'],
            ['everyFourMinutes', '1-59/4 * * * *'],
            ['everyFiveMinutes', '1-59/5 * * * *'],
            ['everyTenMinutes', '1-59/10 * * * *'],
            ['everyFifteenMinutes', '1-59/15 * * * *'],
            ['everyThirtyMinutes', '1-59/30 * * * *'],
        ];
    }

    public function test_it_rephases_an_existing_cadence(): void {
        $this->assertSame(
            '7-59/15 * * * *',
            Cadence::everyFifteenMinutes()->offsetBy(7)->expression(),
        );
    }

    public function test_rephasing_leaves_the_original_untouched(): void {
        $original = Cadence::everyFifteenMinutes();

        $original->offsetBy(7);

        $this->assertSame('*/15 * * * *', $original->expression());
    }

    public function test_it_exposes_its_parts(): void {
        $cadence = Cadence::everyMinutes(15, offsetBy: 8);

        $this->assertSame(15, $cadence->interval()->minutes);

        $this->assertSame(8, $cadence->offset()->minutes);

        $this->assertSame('8-59/15', $cadence->minuteField());
    }

    public function test_equal_cadences_compare_equal(): void {
        $this->assertTrue(Cadence::everyFifteenMinutes(8)->equals(Cadence::everyMinutes(15, 8)));

        $this->assertFalse(Cadence::everyFifteenMinutes(8)->equals(Cadence::everyMinutes(15, 7)));
    }

    public function test_it_rejects_an_interval_that_does_not_divide_the_hour(): void {
        $this->expectException(InvalidInterval::class);

        $this->expectExceptionMessage('divide 60 evenly');

        Cadence::everyMinutes(7);
    }

    public function test_it_rejects_an_hour_or_longer_interval(): void {
        $this->expectException(InvalidInterval::class);

        $this->expectExceptionMessage('between 1 and 59 minutes');

        Cadence::everyMinutes(60);
    }

    public function test_it_rejects_an_offset_that_exceeds_the_interval(): void {
        $this->expectException(InvalidOffset::class);

        $this->expectExceptionMessage('between 0 and 15 minutes');

        Cadence::everyMinutes(15, offsetBy: 15);
    }

    public function test_it_rejects_a_negative_offset(): void {
        $this->expectException(InvalidOffset::class);

        Cadence::everyMinutes(15, offsetBy: -1);
    }

    public function test_every_failure_is_an_invalid_argument_exception(): void {
        $this->expectException(InvalidArgumentException::class);

        Cadence::everyMinutes(7);
    }

    /** @return list<int> */
    private function minutesFiring(string $expression): array {
        $cron = new CronExpression($expression);

        return array_values(array_filter(
            range(0, 59),
            fn (int $minute) => $cron->isDue(sprintf('2026-01-01 00:%02d:00', $minute)),
        ));
    }
}
