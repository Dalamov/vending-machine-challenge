<?php

namespace Daniella\VendingMachine\domain\exception;

class OutOfStockException extends VendingMachineException
{
    public function __construct(string $name)
    {
        parent::__construct("Item out of stock: $name");
    }
}

