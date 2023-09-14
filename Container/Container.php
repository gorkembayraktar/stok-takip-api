<?php

use DI\Container;
use Slim\Factory\AppFactory;
use Illuminate\Database\Capsule\Manager as Capsule;

$container = new Container;


$config = include( BASEAPP .'/config/settings.php');

$container->set('db', function () use ($config) {
    $capsule = new Capsule;

    // Burada veritabanı yapılandırmasını yapmalısınız
    $capsule->addConnection($config['db']);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    return $capsule;
});

AppFactory::setContainer($container);