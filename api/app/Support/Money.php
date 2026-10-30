<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class Money
{
    public static function of(int|float|string $amount): string
    {
        return BigDecimal::of((string) $amount)->toScale(2, RoundingMode::HALF_UP)->__toString();
    }

    public static function multiply(int|float|string $amount, int $times): string
    {
        return BigDecimal::of((string) $amount)
            ->multipliedBy($times)
            ->toScale(2, RoundingMode::HALF_UP)
            ->__toString();
    }

    public static function add(int|float|string $left, int|float|string $right): string
    {
        return BigDecimal::of((string) $left)
            ->plus((string) $right)
            ->toScale(2, RoundingMode::HALF_UP)
            ->__toString();
    }

    public static function subtract(int|float|string $left, int|float|string $right): string
    {
        return BigDecimal::of((string) $left)
            ->minus((string) $right)
            ->toScale(2, RoundingMode::HALF_UP)
            ->__toString();
    }

    public static function compare(int|float|string $left, int|float|string $right): int
    {
        return BigDecimal::of((string) $left)->compareTo((string) $right);
    }

    public static function isPositive(int|float|string $amount): bool
    {
        return BigDecimal::of((string) $amount)->isPositive();
    }
}
