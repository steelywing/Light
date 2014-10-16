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
                    '/web/index.php',
                    '/web',
                    '/',
                    'GET',
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
                    '/web',
                    '/web',
                    '/',
                    'GET',
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
                    '/web/index.php',
                    '/web',
                    '/view/',
                    'GET',
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
                    '/web',
                    '/web',
                    '/view/',
                    'GET',
                ),
            ),
            // Rewrite to sub directory
            array(
                array(
                    'SCRIPT_NAME' => '/web/index.php',
                    'REQUEST_URI' => '/web/view/',
                    'REQUEST_METHOD' => 'GET',
                ),
                array(
                    '/web',
                    '/web',
                    '/view/',
                    'GET',
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
