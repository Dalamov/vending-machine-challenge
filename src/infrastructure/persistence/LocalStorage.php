<?php

namespace Daniella\VendingMachine\Infrastructure\Persistence;

use Daniella\VendingMachine\Domain\Entity\VendingMachine;
use Daniella\VendingMachine\Domain\Entity\Item;
use Daniella\VendingMachine\Domain\Entity\Coin;

class LocalStorage
{
    private string $filePath;

    public function __construct(string $filePath = __DIR__ . '/../../../../storage/vending_machine.json')
    {
        $this->filePath = $filePath;

        if (!file_exists($this->filePath)) {
            $this->saveInitialState();
        }
    }

    private function saveInitialState(): void
    {
        $initial = [
            'insertedAmount' => 0.0,
            'items' => [
                'WATER' => ['name' => 'Water', 'price' => 0.65, 'quantity' => 5],
                'JUICE' => ['name' => 'Juice', 'price' => 1.00, 'quantity' => 5],
                'SODA'  => ['name' => 'Soda',  'price' => 1.50, 'quantity' => 5],
            ],
            'availableChange' => [1.00, 0.25, 0.10, 0.05],
        ];

        file_put_contents($this->filePath, json_encode($initial, JSON_PRETTY_PRINT));
    }

    public function load(): VendingMachine
    {
        $data = json_decode(file_get_contents($this->filePath), true);

        $items = [];
        foreach ($data['items'] as $code => $itemData) {
            $items[$code] = new Item(
                $itemData['name'],
                $itemData['price'],
                $itemData['quantity']
            );
        }

        $coins = [];
        foreach ($data['availableChange'] as $value) {
            $coins[] = new Coin((float)$value);
        }

        return new VendingMachine($items, $coins);
    }

    public function save(VendingMachine $machine): void
    {
        $itemsData = [];
        foreach ($machine->getItems() as $code => $item) {
            $itemsData[$code] = [
                'name' => $item->getName(),
                'price' => $item->getPrice(),
                'quantity' => $item->getQuantity(),
            ];
        }

        $coinsData = [];
        foreach ($machine->getAvailableChange() as $coin) {
            $coinsData[] = $coin->getValue();
        }

        $data = [
            'insertedAmount' => $machine->getInsertedAmount(),
            'items' => $itemsData,
            'availableChange' => $coinsData,
        ];

        file_put_contents($this->filePath, json_encode($data, JSON_PRETTY_PRINT));
    }
}