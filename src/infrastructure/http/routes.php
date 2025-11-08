<?php

use Slim\App;
use Daniella\VendingMachine\infrastructure\http\controller\VendingMachineController;
use Daniella\VendingMachine\application\service\VendingMachineService;
use Daniella\VendingMachine\infrastructure\persistence\LocalStorage;

return function (App $app) {
    $storage = new LocalStorage();
    $service = new VendingMachineService($storage);
    $controller = new VendingMachineController($service);

    $app->get('/inventory', [$controller, 'inventory']);
    $app->get('/inserted-amount', [$controller, 'insertedAmount']);
    $app->post('/insert-coin', [$controller, 'insertCoin']);
    $app->post('/select-item', [$controller, 'selectItem']);
    $app->post('/return-coins', [$controller, 'returnCoins']);
    $app->post('/restock', [$controller, 'restock']);
};