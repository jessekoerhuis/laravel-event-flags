<?php

declare(strict_types=1);

namespace JesseKoerhuis\EventFlags\Traits;

use JesseKoerhuis\EventFlags\Exceptions\InvalidFlagException;

/**
 * @phpstan-type FlagValue bool|int|float|string|null
 */
trait HasFlags
{
    /**
     * @var array<string, FlagValue>
     */
    protected array $flags = [];

    /**
     * Apply flags to the current event instance.
     *
     * @param array<int|string, mixed>|string $flags
     */
    public function withFlags(array|string $flags, string ...$additionalFlags): static
    {
        $clone = clone $this;

        if (is_string($flags)) {
            $clone->flags[$flags] = true;

            foreach ($additionalFlags as $additionalFlag) {
                $clone->flags[$additionalFlag] = true;
            }

            return $clone;
        }

        foreach ($flags as $key => $value) {
            if (is_int($key)) {
                $clone->flags[self::normalizeImplicitFlag($value)] = true;

                continue;
            }

            self::assertPrimitiveValue($key, $value);

            $clone->flags[$key] = $value;
        }

        return $clone;
    }

    /**
     * Get the flags on the current event instance.
     *
     * @return array<string, FlagValue>
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * Assert a flag is enabled on the current event instance.
     *
     * Implicit flags (numeric keys) will be treated as enabled when the flag name exists as a value.
     * Explicit flags (string keys) will be treated as enabled when its value is of a primitive type and is "truthy".
     */
    public function isFlagEnabled(string $flag): bool
    {
        return (bool) ($this->flags[$flag] ?? false);
    }

    /**
     * Get a flag from the current event instance, else return a fallback value, which is null by default.
     */
    public function getFlag(string $flag, mixed $default = null): mixed
    {
        return $this->flags[$flag] ?? $default;
    }

    /**
     * Statically dispatch an event with applicable flags.
     *
     * @param array<int|string, mixed> $flags
     * @return array<int, mixed>|null
     */
    public static function dispatchWithFlags(array $flags, mixed ...$arguments): ?array
    {
        $event = new static(...$arguments);

        return event($event->withFlags($flags));
    }

    /**
     * In case of an explicit flag, assert that the given flag value is of a primitive type.
     *
     * @throws InvalidFlagException
     */
    private static function assertPrimitiveValue(string $flag, mixed $value): void
    {
        if (is_scalar($value) || $value === null) {
            return;
        }

        throw new InvalidFlagException(
            "Flag '{$flag}' must contain a primitive value. " . get_debug_type($value) . ' given.',
        );
    }

    /**
     * If the flag entry is numeric, treat the value as the flag name and set its value to true.
     *
     * @throws InvalidFlagException
     */
    private static function normalizeImplicitFlag(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        throw new InvalidFlagException(
            'Numeric flag entries must contain a string or integer flag name, ' . get_debug_type($value) . ' given.',
        );
    }
}
