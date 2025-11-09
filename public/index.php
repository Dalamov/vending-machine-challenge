<?php

use Slim\Factory\AppFactory;
use Daniella\VendingMachine\infrastructure\logger\ErrorLogLogger;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$logger = new ErrorLogLogger();

$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true, $logger);

// Cargar rutas
(require __DIR__ . '/../src/infrastructure/http/routes.php')($app);

$app->run();