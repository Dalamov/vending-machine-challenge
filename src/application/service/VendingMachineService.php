<?php

namespace Daniella\VendingMachine\Application\Service;

use Daniella\VendingMachine\Domain\Entity\Item;
use Daniella\VendingMachine\Infrastructure\Persistence\LocalStorage;
use Daniella\VendingMachine\Domain\Exception\OutOfStockException;
use Daniella\VendingMachine\Domain\Exception\InvalidSelectionException;
use Daniella\VendingMachine\Domain\Helper\CoinDenominations;

class VendingMachineService
{
    private LocalStorage $storage;
    private array $insertedCoins = [];

    public function __construct(LocalStorage $storage)
    {
        $this->storage = $storage;
    }

 
    public function insertCoin(float $value): void
    {
        if (!CoinDenominations::isValid($value)) {
            throw new \InvalidArgumentException("Invalid coin: $value");
        }

        $this->insertedCoins[] = $value;
    }


    public function selectItem(string $itemName): Item
    {
        $machine = $this->storage->load();
        $items = $machine->getItems();

        if (!isset($items[$itemName])) {
            throw new InvalidSelectionException("Item '$itemName' not found.");
        }

        $item = $items[$itemName];
        $totalInserted = array_sum($this->insertedCoins);

        if ($item->getQuantity() <= 0) {
            throw new OutOfStockException("Item '{$item->getName()}' is out of stock.");
        }

        if ($totalInserted < $item->getPrice()) {
            throw new \RuntimeException("Insufficient funds. Insert more coins.");
        }

        $item->decreaseQuantity();
        $items[$itemName] = $item;
        $machine->setItems($items);
        $this->storage->save($machine);

        $this->insertedCoins = [];

        return $item;
    }


    public function returnCoins(): array
    {
        $coins = $this->insertedCoins;
        $this->insertedCoins = [];
        return $coins;
    }

    public function restockItem(string $itemName, int $amount): void
    {
        $machine = $this->storage->load();
        $items = $machine->getItems();

        if (!isset($items[$itemName])) {
            throw new InvalidSelectionException("Item '$itemName' not found for restock.");
        }

        $item = $items[$itemName];
        $item->increaseQuantity($amount);
        $items[$itemName] = $item;

        $machine->setItems($items);
        $this->storage->save($machine);
    }

    public function getInventory(): array
    {
        return $this->storage->load()->getItems();
    }
}