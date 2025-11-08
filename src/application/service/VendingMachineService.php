<?php

namespace Daniella\VendingMachine\application\service;

use Daniella\VendingMachine\Infrastructure\Persistence\LocalStorage;
use Daniella\VendingMachine\Domain\Exception\OutOfStockException;
use Daniella\VendingMachine\Domain\Exception\InvalidSelectionException;
use Daniella\VendingMachine\Domain\Helper\CoinDenominations;
use Daniella\VendingMachine\Application\Response\SuccessResponse;
use Daniella\VendingMachine\Application\Response\ErrorResponse;
use Daniella\VendingMachine\Application\Response\VendingResponse;

class VendingMachineService
{
    private LocalStorage $storage;

    public function __construct(LocalStorage $storage)
    {
        $this->storage = $storage;
    }

    public function insertCoin(float $value): VendingResponse
    {
        try {
            if (!CoinDenominations::isValid($value)) {
                throw new \InvalidArgumentException("Invalid coin: $value");
            }

            $machine = $this->storage->load();
            $insertedAmount = $machine->getInsertedAmount() + $value;
            $machine->setInsertedAmount($insertedAmount);
            $this->storage->save($machine);

            return new SuccessResponse(
                "Coin inserted successfully.",
                ['insertedAmount' => $insertedAmount]
            );
        } catch (\Throwable $e) {
            return new ErrorResponse($e->getMessage());
        }
    }

    public function selectItem(string $itemName): VendingResponse
    {
        try {
            $machine = $this->storage->load();
            $items = $machine->getItems();

            $normalizedKey = strtoupper($itemName);

            if (!isset($items[$normalizedKey])) {
                throw new InvalidSelectionException("Item '$itemName' not found.");
            }

            $item = $items[$normalizedKey];
            $totalInserted = $machine->getInsertedAmount();

            if ($item->getQuantity() <= 0) {
                throw new OutOfStockException("Item '{$item->getName()}' is out of stock.");
            }

            if ($totalInserted < $item->getPrice()) {
                throw new \RuntimeException("Insufficient funds. Insert more coins.");
            }

            $item->decreaseQuantity();
            $items[$normalizedKey] = $item;
            $machine->setItems($items);
            $change = round($totalInserted - $item->getPrice(), 2);
            $machine->setInsertedAmount($change);
            $this->storage->save($machine);

            return new SuccessResponse(
                "Item '{$item->getName()}' dispensed successfully.",
                [
                    'item' => $item->getName(),
                    'price' => $item->getPrice(),
                    'change' => $change
                ]
            );
        } catch (\Throwable $e) {
            return new ErrorResponse($e->getMessage());
        }
    }

    public function returnCoins(): VendingResponse
    {
        $machine = $this->storage->load();
        $totalInserted = $machine->getInsertedAmount();

        if ($totalInserted <= 0) {
            return new ErrorResponse("No coins to return.");
        }

        $machine->setInsertedAmount(0.0);
        $this->storage->save($machine);

        return new SuccessResponse("Coins returned.", ['amount' => $totalInserted]);
    }

    public function restockItem(string $itemName, int $amount): VendingResponse
    {
        try {
            $machine = $this->storage->load();
            $items = $machine->getItems();

            $normalizedKey = strtoupper($itemName);

            if (!isset($items[$normalizedKey])) {
                throw new InvalidSelectionException("Item '$itemName' not found for restock.");
            }

            $item = $items[$normalizedKey];
            $item->increaseQuantity($amount);
            $items[$normalizedKey] = $item;

            $machine->setItems($items);
            $this->storage->save($machine);

            return new SuccessResponse("Item restocked successfully.", [
                'item' => $item->getName(),
                'newQuantity' => $item->getQuantity()
            ]);
        } catch (\Throwable $e) {
            return new ErrorResponse($e->getMessage());
        }
    }

    public function getInventory(): VendingResponse
    {
        try {
            $items = $this->storage->load()->getItems();
            $inventory = [];

            foreach ($items as $key => $item) {
                $inventory[$key] = [
                    'name' => $item->getName(),
                    'price' => $item->getPrice(),
                    'quantity' => $item->getQuantity(),
                ];
            }

            return new SuccessResponse("Inventory retrieved.", ['items' => $inventory]);
        } catch (\Throwable $e) {
            return new ErrorResponse($e->getMessage());
        }
    }

    public function getInsertedAmount(): VendingResponse
    {
        try {
            $amount = $this->storage->load()->getInsertedAmount();

            return new SuccessResponse("Inserted amount retrieved.", [
                'insertedAmount' => $amount
            ]);
        } catch (\Throwable $e) {
            return new ErrorResponse($e->getMessage());
        }
    }
}