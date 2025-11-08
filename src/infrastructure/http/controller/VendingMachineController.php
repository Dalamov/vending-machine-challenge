<?php

namespace Daniella\VendingMachine\infrastructure\http\controller;

use Daniella\VendingMachine\application\service\VendingMachineService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class VendingMachineController
{
    private VendingMachineService $service;

    public function __construct(VendingMachineService $service)
    {
        $this->service = $service;
    }

    /**
     * Helper para responder en JSON
     */
    private function respond(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    public function insertCoin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array)$request->getParsedBody();
        $value = isset($body['value']) ? (float)$body['value'] : 0;

        $result = $this->service->insertCoin($value);
        return $this->respond($response, $result->toArray());
    }

    public function selectItem(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array)$request->getParsedBody();
        $itemName = isset($body['item']) ? (string)$body['item'] : '';
        $result = $this->service->selectItem($itemName);
        return $this->respond($response, $result->toArray());
    }

    public function returnCoins(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $result = $this->service->returnCoins();
        return $this->respond($response, $result->toArray());
    }

    public function restock(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array)$request->getParsedBody();
        $amount = isset($body['amount']) ? (int)$body['amount'] : 0;
        $itemName = isset($body['item']) ? (string)$body['item'] : '';

        $result = $this->service->restockItem($itemName, $amount);
        return $this->respond($response, $result->toArray());
    }

    public function inventory(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $result = $this->service->getInventory();
        return $this->respond($response, $result->toArray());
    }

    public function insertedAmount(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $result = $this->service->getInsertedAmount();
        return $this->respond($response, $result->toArray());
    }
}