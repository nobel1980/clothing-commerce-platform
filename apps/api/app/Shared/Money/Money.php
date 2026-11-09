<?php

namespace App\Shared\Money;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency = 'BDT',
    ) {
        if ($currency === '') {
            throw new InvalidArgumentException('Currency code is required.');
        }
    }

    public static function zero(string $currency = 'BDT'): self
    {
        return new self(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self(max(0, $this->amount - $other->amount), $this->currency);
    }

    public function multiply(int $multiplier): self
    {
        return new self($this->amount * $multiplier, $this->currency);
    }

    public function format(): string
    {
        $major = intdiv($this->amount, 100);
        $minor = abs($this->amount % 100);
        $symbol = match (strtoupper($this->currency)) {
            'BDT'   => '৳',
            'USD'   => '$',
            'EUR'   => '€',
            default => strtoupper($this->currency) . ' ',
        };

        return sprintf('%s%s.%02d', $symbol, number_format($major), $minor);
    }

    public function formatted(): string
    {
        return $this->format();
    }

    public function toMajorUnits(): string
    {
        $major = intdiv($this->amount, 100);
        $minor = abs($this->amount % 100);

        return sprintf('%d.%02d', $major, $minor);
    }

    public function toArray(): array
    {
        return [
            'amount'    => $this->amount,
            'currency'  => $this->currency,
            'formatted' => $this->format(),
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Currency mismatch: {$this->currency} vs {$other->currency}."
            );
        }
    }
}

