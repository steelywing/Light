<?php

namespace Light\Tests;

use Light\Router;

class RouterTest extends \PHPUnit_Framework_TestCase
{
    public function testGetInstance()
    {
        $router = Router::getInstance();
        $this->assertSame($router, Router::getInstance());

        $router = new Router();
        $this->assertSame($router, Router::getInstance());
    }

    public function uriProvider()
    {
        return array(
            // Explicit index.php
            array(
                array(
                    'SCRIPT_NAME' => '/web/index.php',
                    'REQUEST_URI' => '/web/index.php',
                    'REQUEST_METHOD' => 'GET',
                ),
                array(
                    'script.name' => '/web/index.php',
                    'script.dir' => '/web',
                    'path' => '/',
                    'request.method' => 'GET',
                ),
            ),
            // Implicit index.php
            array(
                array(
                    'SCRIPT_NAME' => '/web/index.php',
                    'REQUEST_URI' => '/web/',
                    'REQUEST_METHOD' => 'GET',
                ),
                array(
                    'script.name' => '/web',
                    'script.dir' => '/web',
                    'path' => '/',
                    'request.method' => 'GET',
                ),
            ),
            // Explicit index.php with path
            array(
                array(
                    'SCRIPT_NAME' => '/web/index.php',
                    'REQUEST_URI' => '/web/index.php/view/',
                    'REQUEST_METHOD' => 'GET',
                ),
                array(
                    'script.name' => '/web/index.php',
                    'script.dir' => '/web',
                    'path' => '/view/',
                    'request.method' => 'GET',
                ),
            ),
            // Apache rewrite with path
            array(
                array(
                    'SCRIPT_NAME' => '/web/index.php',
                    'REQUEST_URI' => '/web/view/',
                    'REQUEST_METHOD' => 'GET',
                ),
                array(
                    'script.name' => '/web',
                    'script.dir' => '/web',
                    'path' => '/view/',
                    'request.method' => 'GET',
                ),
            ),
            // Rewrite to sub directory
            array(
                array(
                    'SCRIPT_NAME' => '/web/index.php',
                    'REQUEST_URI' => '/view/',
                    'REQUEST_METHOD' => 'GET',
                ),
                array(
                    'script.name' => '/web',
                    'script.dir' => '/web',
                    'path' => '/view/',
                    'request.method' => 'GET',
                ),
            ),
        );
    }

    /**
     * @dataProvider uriProvider
     * @param $routes
     * @param $env
     * @internal param $route
     */
    public function testParseUri($routes, $env)
    {
        $router = new Router($routes);

        $this->assertEquals($env[0], $router->getScriptName());
        $this->assertEquals($env[1], $router->getScriptDir());
        $this->assertEquals($env[2], $router->getPath());
        $this->assertEquals($env[3], $router->getRequestMethod());
    }
}
