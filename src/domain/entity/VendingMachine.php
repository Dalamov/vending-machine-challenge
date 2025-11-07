<?php

namespace Daniella\VendingMachine\Domain\Entity;

class VendingMachine
{
    private float $insertedAmount;
    private array $items;
    private array $availableChange = [];

    public function __construct(array $items = [], array $availableChange = [])
    {
        $this->items = $items;
        $this->availableChange = $availableChange;
        $this->insertedAmount = 0.0;
    }

    public function getInsertedAmount(): float
    {
        return $this->insertedAmount;
    }

    public function setInsertedAmount(float $amount): void
    {
        $this->insertedAmount = $amount;
    }

    /**
     * @return Item[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function getAvailableChange(): array
    {
        return $this->availableChange;
    }

    public function setAvailableChange(array $change): void
    {
        $this->availableChange = $change;
    }

    public function getItem(string $code): ?Item
    {
        return $this->items[$code] ?? null;
    }
}