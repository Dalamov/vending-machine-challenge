<?php

namespace Tests\application\service;

use PHPUnit\Framework\TestCase;
use Daniella\VendingMachine\application\service\VendingMachineService;
use Daniella\VendingMachine\infrastructure\persistence\LocalStorage;
use Daniella\VendingMachine\Application\Response\SuccessResponse;
use Daniella\VendingMachine\Application\Response\ErrorResponse;

class VendingMachineServiceTest extends TestCase
{
    private string $storagePath;
    private VendingMachineService $service;

    protected function setUp(): void
    {
        $this->storagePath = tempnam(sys_get_temp_dir(), 'vm_');
        if ($this->storagePath === false) {
            self::fail('Unable to create temporary storage file.');
        }

        if (file_exists($this->storagePath)) {
            unlink($this->storagePath);
        }

        $storage = new LocalStorage($this->storagePath);
        $this->service = new VendingMachineService($storage);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->storagePath)) {
            unlink($this->storagePath);
        }
    }

    public function testInsertCoinStoresAmount(): void
    {
        $response = $this->service->insertCoin(1.00);

        $this->assertInstanceOf(SuccessResponse::class, $response);
        $this->assertTrue($response->isSuccess());
        $this->assertSame(1.00, $response->getData()['insertedAmount']);

        $balance = $this->service->getInsertedAmount();
        $this->assertInstanceOf(SuccessResponse::class, $balance);
        $this->assertSame(1.00, $balance->getData()['insertedAmount']);
    }

    public function testInsertCoinRejectsInvalidDenomination(): void
    {
        $response = $this->service->insertCoin(0.03);

        $this->assertInstanceOf(ErrorResponse::class, $response);
        $this->assertFalse($response->isSuccess());
        $this->assertSame('Invalid coin: 0.03', $response->getMessage());
    }

    public function testSelectItemDispensesProductWithChange(): void
    {
        $this->service->insertCoin(1.00);
        $this->service->insertCoin(0.25);

        $response = $this->service->selectItem('Water');

        $this->assertInstanceOf(SuccessResponse::class, $response);
        $this->assertTrue($response->isSuccess());

        $data = $response->getData();
        $this->assertSame('Water', $data['item']);
        $this->assertSame(0.65, $data['price']);
        $this->assertEqualsCanonicalizing([0.25, 0.25, 0.10], $data['change']);

        $balance = $this->service->getInsertedAmount();
        $this->assertSame(0.0, $balance->getData()['insertedAmount']);
    }

    public function testSelectItemFailsWithInsufficientFunds(): void
    {
        $response = $this->service->selectItem('Water');

        $this->assertInstanceOf(ErrorResponse::class, $response);
        $this->assertFalse($response->isSuccess());
        $this->assertSame('Insufficient funds. Insert more coins.', $response->getMessage());
    }

    public function testReturnCoinsGivesBackInsertedCoins(): void
    {
        $this->service->insertCoin(0.25);
        $this->service->insertCoin(0.10);

        $response = $this->service->returnCoins();

        $this->assertInstanceOf(SuccessResponse::class, $response);
        $this->assertTrue($response->isSuccess());
        $this->assertEquals([0.25, 0.10], $response->getData()['coins']);

        $balance = $this->service->getInsertedAmount();
        $this->assertSame(0.0, $balance->getData()['insertedAmount']);
    }
}

