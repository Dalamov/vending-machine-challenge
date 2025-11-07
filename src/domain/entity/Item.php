<?php

namespace Daniella\VendingMachine\Domain\Entity;

class Item
{
    private string $name;
    private float $price;
    private int $quantity;

    public function __construct(string $name, float $price, int $quantity)
    {
        $this->name = $name;
        $this->price = $price;
        $this->quantity = $quantity;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function decreaseQuantity(): void
    {
        if ($this->quantity <= 0) {
            throw new \RuntimeException("Item '{$this->name}' is out of stock.");
        }
        $this->quantity--;
    }

    public function increaseQuantity(int $amount): void
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException("Cannot increase quantity by a negative number.");
        }
        $this->quantity += $amount;
    }
}