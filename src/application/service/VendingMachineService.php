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
                $changeResult = $this->dispenseChange($availableCoins, $changeAmount);
                $changeCoins = $changeResult['coins'];
                $remainingCoins = $changeResult['remaining'];
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
     * @return array{coins: array<float>, remaining: array<float>}
     */
    private function dispenseChange(array $availableCoins, float $amount): array
    {
        $denominations = [1.00, 0.25, 0.10, 0.05];
        $counts = $this->buildCoinCounts($availableCoins);
        $changeCoins = [];
        $remainingAmount = $amount;

        foreach ($denominations as $denomination) {
            $key = $this->formatCoinKey($denomination);
            $available = $counts[$key] ?? 0;

            while ($available > 0 && $this->canUseCoin($remainingAmount, $denomination)) {
                $changeCoins[] = $denomination;
                $remainingAmount = round($remainingAmount - $denomination, 2);
                $available--;
            }

            $counts[$key] = $available;
        }

        if ($remainingAmount > 0) {
            throw new \RuntimeException("Unable to provide change for {$amount}.");
        }

        return [
            'coins' => $changeCoins,
            'remaining' => $this->expandCountsToCoins($counts),
        ];
    }

    /**
     * @param array<float> $coins
     * @return array<string, int>
     */
    private function buildCoinCounts(array $coins): array
    {
        $counts = [];
        foreach ($coins as $coin) {
            if (!CoinDenominations::isValid($coin)) {
                continue;
            }

            $key = $this->formatCoinKey($coin);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    private function formatCoinKey(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function canUseCoin(float $remaining, float $coin): bool
    {
        return round($remaining + 0.00001, 2) >= $coin;
    }

    /**
     * @param array<string, int> $counts
     * @return array<float>
     */
    private function expandCountsToCoins(array $counts): array
    {
        $coins = [];
        $denominations = [1.00, 0.25, 0.10, 0.05];

        foreach ($denominations as $denomination) {
            $key = $this->formatCoinKey($denomination);
            $count = $counts[$key] ?? 0;
            for ($i = 0; $i < $count; $i++) {
                $coins[] = $denomination;
            }
        }

        return $coins;
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