<?php

namespace Daniella\VendingMachine\domain\entity;

final class Coin
{
    private float $value;

    public function __construct(float $value)
    {
        $this->value = $value;
    }

    public function getValue(): float
    {
        return $this->value;
    }
}