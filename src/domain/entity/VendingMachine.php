<?php

namespace Daniella\VendingMachine\domain\entity;

class VendingMachine
{
    private float $insertedAmount;
    private array $items;
    private array $availableChange = [];
    private array $insertedCoins = [];

    public function __construct(array $items = [], array $availableChange = [], array $insertedCoins = [])
    {
        $this->items = $items;
        $this->availableChange = $availableChange;
        $this->insertedAmount = 0.0;
        $this->setInsertedCoins($insertedCoins);
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

    public function getInsertedCoins(): array
    {
        return $this->insertedCoins;
    }

    public function setInsertedCoins(array $coins): void
    {
        $this->insertedCoins = array_map('floatval', $coins);
    }

    public function addInsertedCoin(float $coin): void
    {
        $this->insertedCoins[] = $coin;
    }

    public function clearInsertedCoins(): void
    {
        $this->insertedCoins = [];
    }
}