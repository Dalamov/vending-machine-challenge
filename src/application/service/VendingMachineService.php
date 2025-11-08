<?php

namespace Daniella\VendingMachine\application\service;

use Daniella\VendingMachine\infrastructure\persistence\LocalStorage;
use Daniella\VendingMachine\domain\exception\OutOfStockException;
use Daniella\VendingMachine\domain\exception\InvalidSelectionException;
use Daniella\VendingMachine\domain\helper\CoinDenominations;
use Daniella\VendingMachine\domain\entity\Coin;
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
            $machine->addInsertedCoin($value);
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
            $insertedCoins = $machine->getInsertedCoins();

            if ($item->getQuantity() <= 0) {
                throw new OutOfStockException("Item '{$item->getName()}' is out of stock.");
            }

            if ($totalInserted < $item->getPrice()) {
                throw new \RuntimeException("Insufficient funds. Insert more coins.");
            }

            $item->decreaseQuantity();
            $items[$normalizedKey] = $item;
            $machine->setItems($items);

            $availableCoins = array_map(
                static fn (Coin $coin) => $coin->getValue(),
                $machine->getAvailableChange()
            );
            $availableCoins = array_merge($availableCoins, $insertedCoins);

            $changeAmount = round($totalInserted - $item->getPrice(), 2);
            $changeCoins = [];
            $remainingCoins = $availableCoins;

            if ($changeAmount > 0) {
                $changeCoins = $this->makeChange($availableCoins, $changeAmount);
                $remainingCoins = $this->removeDispensedCoins($availableCoins, $changeCoins);
            }

            $machine->setAvailableChange($this->buildCoinEntities($remainingCoins));
            $machine->setInsertedAmount(0.0);
            $machine->clearInsertedCoins();
            $this->storage->save($machine);

            return new SuccessResponse(
                "Item '{$item->getName()}' dispensed successfully.",
                [
                    'item' => $item->getName(),
                    'price' => $item->getPrice(),
                    'change' => $changeCoins
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

        $coins = $machine->getInsertedCoins();
        $machine->setInsertedAmount(0.0);
        $machine->clearInsertedCoins();
        $this->storage->save($machine);

        return new SuccessResponse("Coins returned.", ['coins' => $coins]);
    }

    public function restockItem(string $itemName, int $amount, ?array $changeConfig = null): VendingResponse
    {
        try {
            $machine = $this->storage->load();
            $items = $machine->getItems();

            $normalizedKey = strtoupper($itemName);
            $responseData = [];

            if ($itemName !== '') {
                if (!isset($items[$normalizedKey])) {
                    throw new InvalidSelectionException("Item '$itemName' not found for restock.");
                }

                $item = $items[$normalizedKey];
                $item->increaseQuantity($amount);
                $items[$normalizedKey] = $item;
                $machine->setItems($items);

                $responseData['item'] = $item->getName();
                $responseData['newQuantity'] = $item->getQuantity();
            }

            if ($changeConfig !== null) {
                $machine->setAvailableChange(
                    $this->buildCoinEntities(
                        $this->expandChangeConfiguration($changeConfig)
                    )
                );
                $responseData['availableChange'] = $changeConfig;
            }

            if (empty($responseData)) {
                throw new \InvalidArgumentException('No restock or change configuration provided.');
            }

            $this->storage->save($machine);

            return new SuccessResponse("Service update completed.", $responseData);
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

    /**
     * @param array<float> $availableCoins
     * @return array<float>
     */
    private function makeChange(array $availableCoins, float $amount): array
    {
        $sorted = $availableCoins;
        rsort($sorted, SORT_NUMERIC);

        $change = [];
        $remaining = $amount;

        foreach ($sorted as $coin) {
            if ($remaining <= 0) {
                break;
            }

            if ($coin <= $remaining + 0.0001) {
                $change[] = $coin;
                $remaining = round($remaining - $coin, 2);
            }
        }

        if ($remaining > 0) {
            throw new \RuntimeException("Unable to provide change for {$amount}.");
        }

        return $change;
    }

    /**
     * @param array<float> $available
     * @param array<float> $dispensed
     * @return array<float>
     */
    private function removeDispensedCoins(array $available, array $dispensed): array
    {
        $remaining = $available;
        foreach ($dispensed as $coin) {
            $index = array_search($coin, $remaining, true);
            if ($index === false) {
                continue;
            }
            unset($remaining[$index]);
        }

        return array_values($remaining);
    }

    /**
     * @param array<float> $values
     * @return array<Coin>
     */
    private function buildCoinEntities(array $values): array
    {
        return array_map(static fn ($value) => new Coin($value), $values);
    }

    /**
     * @param array<string, int|float> $configuration
     * @return array<float>
     */
    private function expandChangeConfiguration(array $configuration): array
    {
        $coins = [];

        foreach ($configuration as $value => $count) {
            $coinValue = (float)$value;
            if (!CoinDenominations::isValid($coinValue)) {
                throw new \InvalidArgumentException("Invalid coin denomination provided: {$value}");
            }

            $intCount = (int)$count;
            if ($intCount < 0) {
                throw new \InvalidArgumentException("Coin count must be positive for denomination {$value}");
            }

            for ($i = 0; $i < $intCount; $i++) {
                $coins[] = $coinValue;
            }
        }

        return $coins;
    }
}