<?php

namespace Daniella\VendingMachine\domain\helper;

final class CoinDenominations
{
    public const VALID = [0.05, 0.10, 0.25, 1.00];

    public static function isValid(float $value): bool
    {
        return in_array($value, self::VALID, true);
    }
}