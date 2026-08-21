<?php

namespace App\Domain\Platform\Support;

use InvalidArgumentException;
use Stringable;

/**
 * Amounts are integer Ariary. Never floats. Currency is always MGA in the MVP.
 */
final class Money implements Stringable
{
    public const CURRENCY = 'MGA';

    public function __construct(public readonly int $amount)
    {
        if ($this->amount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }
    }

    public static function of(int $amount): self
    {
        return new self($amount);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function minus(self $other): self
    {
        if ($other->amount > $this->amount) {
            throw new InvalidArgumentException('Money subtraction would be negative.');
        }

        return new self($this->amount - $other->amount);
    }

    public function currency(): string
    {
        return self::CURRENCY;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function __toString(): string
    {
        return number_format($this->amount, 0, ',', ' ').' Ar';
    }
}
