<?php

use Slim\App;
use DI\Container;
use DI\Bridge\Slim\Bridge;
use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use AS2\Server;

require __DIR__.'/../vendor/autoload.php';

// 1. BASIC CONFIGURATION
// ini_set('display_errors', '1');
// error_reporting(E_ALL);

$settings = require __DIR__ . '/../config/settings.php';
require __DIR__ . '/dependencies.php';
$container = new Container(
    _getContainerArr($settings)
);

// 'MessageRepository' =>  new MessageRepository([
//     'path' => $c['storage']['path'] . '/messages',
// ]),
// 'PartnerRepository' =>  new PartnerRepository([
//     require __DIR__ . '/../config/partners.php'
// ]),
// 'Logger' => $logger,
// 'manager' => _newManager($

$server = new Server(
    $container->get('manager'),
    $container->get('PartnerRepository'),
    $container->get('MessageRepository')
);
$container->set(Server::class, $server);

AppFactory::setContainer($container);

$app = Bridge::create($container);


// 4. ADD GLOBAL MIDDLEWARE
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(
    $settings['displayErrorDetails'] ?? false,
    $settings['logErrors'] ?? true,
    $settings['logErrorDetails'] ?? true
);

// 6. REGISTER ROUTES
$routes = require __DIR__.'/routes.php';
$routes($app);
