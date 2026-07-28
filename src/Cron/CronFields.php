<?php

namespace Faerber\KernelCadence\Cron;

use Faerber\KernelCadence\Exceptions\UnsupportedMinuteField;
use Faerber\KernelCadence\MinuteOffset;
use Stringable;

/**
 * The five fields of a cron expression, split so the minute field can be
 * rewritten without disturbing the hour, day, month and weekday it came with.
 */
final readonly class CronFields implements Stringable {
    private const FIELD_COUNT = 5;
    private const MINUTE = 0;

    /** @param list<string> $fields */
    private function __construct(private array $fields) {
    }

    public static function parse(string $expression): self {
        $fields = preg_split('/\s+/', trim($expression), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($fields) !== self::FIELD_COUNT) {
            throw UnsupportedMinuteField::notFiveFields($expression, count($fields));
        }

        return new self($fields);
    }

    public function minuteField(): MinuteField {
        return (new MinuteFieldParser())->parse($this->fields[self::MINUTE]);
    }

    /** The raw minute field, including forms this package cannot re-phase. */
    public function minuteFieldExpression(): string {
        return $this->fields[self::MINUTE];
    }

    public function offsetBy(MinuteOffset $offset): self {
        return $this->withMinuteField($this->minuteField()->offsetBy($offset));
    }

    public function withMinuteField(MinuteField $field): self {
        $fields = $this->fields;
        $fields[self::MINUTE] = $field->toExpression();

        return new self($fields);
    }

    /** True when the hour field is unrestricted, so the cadence repeats every hour. */
    public function repeatsEveryHour(): bool {
        return $this->fields[1] === '*';
    }

    public function toExpression(): string {
        return implode(' ', $this->fields);
    }

    public function __toString(): string {
        return $this->toExpression();
    }
}
