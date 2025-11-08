<?php

namespace tests\application\response;

use PHPUnit\Framework\TestCase;
use Daniella\VendingMachine\application\response\VendingResponse;
use Daniella\VendingMachine\application\response\SuccessResponse;
use Daniella\VendingMachine\application\response\ErrorResponse;

class VendingResponseTest extends TestCase
{
    public function testSuccessResponseCreatesValidResponse(): void
    {
        $data = ['item' => 'Water', 'change' => [1.00, 0.25]];
        $response = new SuccessResponse('Item dispensed successfully', $data);

        $this->assertInstanceOf(VendingResponse::class, $response);
        $this->assertTrue($response->isSuccess());
        $this->assertEquals('Item dispensed successfully', $response->getMessage());
        $this->assertEquals($data, $response->getData());
    }

    public function testErrorResponseCreatesValidResponse(): void
    {
        $response = new ErrorResponse('Item not available');

        $this->assertInstanceOf(VendingResponse::class, $response);
        $this->assertFalse($response->isSuccess());
        $this->assertEquals('Item not available', $response->getMessage());
        $this->assertEquals([], $response->getData());
    }

    public function testToArrayReturnsExpectedStructure(): void
    {
        $data = ['item' => 'Juice'];
        $response = new SuccessResponse('OK', $data);

        $expected = [
            'success' => true,
            'message' => 'OK',
            'data' => ['item' => 'Juice']
        ];

        $this->assertEquals($expected, $response->toArray());
    }
}