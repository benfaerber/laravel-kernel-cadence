<?php

namespace Faerber\KernelCadence\Cron;

use Faerber\KernelCadence\Exceptions\UnsupportedMinuteField;
use Faerber\KernelCadence\MinuteInterval;
use Faerber\KernelCadence\MinuteOffset;

/**
 * Reads the minute field Laravel's own frequency helpers produce back into a
 * MinuteField, so an existing schedule line can be re-phased in place.
 */
final readonly class MinuteFieldParser {
    private const WILDCARD = '*';
    private const UNPHASED_STEP = '/^\*\/(\d+)$/';
    private const PHASED_STEP = '/^(\d+)-(\d+)\/(\d+)$/';
    private const MINUTE_LIST = '/^\d+(,\d+)*$/';

    public function parse(string $field): MinuteField {
        $field = trim($field);

        return $this->asWildcard($field)
            ?? $this->asUnphasedStep($field)
            ?? $this->asPhasedStep($field)
            ?? $this->asMinuteList($field)
            ?? throw UnsupportedMinuteField::unparsable($field);
    }

    private function asWildcard(string $field): ?MinuteField {
        return $field === self::WILDCARD ? EveryMinute::wildcard() : null;
    }

    private function asUnphasedStep(string $field): ?MinuteField {
        if (preg_match(self::UNPHASED_STEP, $field, $matches) !== 1) {
            return null;
        }

        return StepMinutes::every(MinuteInterval::of((int) $matches[1]));
    }

    /**
     * Only a range that runs to the end of the hour is a phase of a whole-hour
     * cadence; '0-30/5' is a window, and shifting it would change its meaning.
     */
    private function asPhasedStep(string $field): ?MinuteField {
        if (preg_match(self::PHASED_STEP, $field, $matches) !== 1) {
            return null;
        }

        if ((int) $matches[2] !== MinuteInterval::LAST_MINUTE) {
            return null;
        }

        return StepMinutes::every(
            MinuteInterval::of((int) $matches[3]),
            MinuteOffset::of((int) $matches[1]),
        );
    }

    private function asMinuteList(string $field): ?MinuteField {
        if (preg_match(self::MINUTE_LIST, $field) !== 1) {
            return null;
        }

        return ExactMinutes::at(array_map('intval', explode(',', $field)));
    }
}
