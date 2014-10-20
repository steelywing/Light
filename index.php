<?php

include_once "vendor/autoload.php";

use Light\Router;

$router = Router::getInstance();
$router->get(function () {
	echo 'Home';
});

$router->setNotFound(function () use ($router) {
    print_r($router->env);
    print_r($_SERVER);
});

$router->run();
