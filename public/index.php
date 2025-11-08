<?php

use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->addBodyParsingMiddleware();

// Cargar rutas
(require __DIR__ . '/../src/infrastructure/http/routes.php')($app);

$app->run();