<?php
/**
 * @author      Wing Leong <steely.wing@gmail.com>
 * @copyright   Wing Leong
 * @license     MIT public license
 */

/**
 *
 * Light Router
 *
 * Base on Bramus Router class
 * https://github.com/bramus/router
 *
 */

namespace Light;

class Router
{
    /**
     * @var self Instance of self
     */
    private static $instance = null;


    /**
     * @var array Callable instances, every class will only create 1 instance
     */
    public $controllers = array();


    /**
     * @var array Route pattern replace list
     */
    public $patternTokens = array(
        ':alpha' => '[a-zA-Z]+',
        ':number' => '[0-9]+',
        ':string' => '[a-zA-Z0-9-_]+',
    );


    /**
     * @var array Environment
     */
    public $env = array();


    /**
     * @var array The route patterns and their handling functions
     */
    private $routes = array();


    /**
     * @var array The before middleware route
     */
    private $beforeRoutes = array();


    /**
     * @var array The after middleware route
     */
    private $afterRoutes = array();


    /**
     * @var callable The function to be executed when no route has been matched
     */
    private $notFound = null;


    /**
     * @param array $env
     */
    public function __construct($env = null)
    {
        if (is_null($env)) {
            $env = array(
                'SCRIPT_NAME' => '',
                'REQUEST_URI' => '',
                'REQUEST_METHOD' => '',
            );

            // Set default value to $_SERVER
            foreach ($env as $key => &$value) {
                if (isset($_SERVER[$key])) {
                    $value = $_SERVER[$key];
                }
            }
            unset($value);
        }

        // Script directory URI
        $this->env['script.dir'] = str_replace(
            '\\', '/',
            dirname($env['SCRIPT_NAME'])
        );

        // Get script URI
        if (strpos($env['REQUEST_URI'], $env['SCRIPT_NAME']) === 0) {
            // Without URL rewrite
            $this->env['script.name'] = $env['SCRIPT_NAME'];
        } else {
            // With URL rewrite
            $this->env['script.name'] = $this->env['script.dir'];
        }

        // Get path trailing the script URI
        $path = substr_replace(
            $env['REQUEST_URI'], '',
            0, strlen($this->env['script.name'])
        );

        // Remove query string
        $query = strpos($path, '?');
        if ($query !== false) {
            $path = substr_replace($path, '', $query);
        }

        $this->env['path'] = '/' . ltrim($path, '/');
        $this->env['request.method'] = $env['REQUEST_METHOD'];

        self::$instance = $this;
    }

    /**
     * Get a instance of self
     *
     * @param null $env
     * @return self
     */
    public static function getInstance($env = null)
    {
        if (is_null(self::$instance)) {
            new self($env);
        }
        return self::$instance;
    }


    /**
     * Get the callable object
     *
     * @param callable|string The callable, can be string 'Class->method', only auto create 1 instance for each class
     * @throws \InvalidArgumentException
     * @return callable
     */
    private function getCallable($callable)
    {
        if (is_callable($callable)) {
            return $callable;
        }

        // Parse 'Class->method'
        if (is_string($callable) && strpos($callable, '->') !== false) {
            list($class, $method) = explode('->', $callable, 2);

            if (!class_exists($class)) {
                throw new \InvalidArgumentException("Class <{$class}> not exist.");
            }

            // Search controller instance, we do not use key to search, because
            // we can not get the fully qualified class name before PHP 5.5 (ClassName::class)
            $controller = null;
            foreach ($this->controllers as $thisController) {
                if ($thisController instanceof $class) {
                    $controller = $thisController;
                    break;
                }
            }

            // Create controller instance if not found
            if (is_null($controller)) {
                $controller = new $class();
                $this->controllers[] = $controller;
            }

            $controllerMethod = array($controller, $method);
            if (is_callable($controllerMethod)) {
                return $controllerMethod;
            }
        }

        $message = is_array($callable) ? print_r($callable, true) : $callable;
        throw new \BadFunctionCallException("'{$message}' is not callable.");
    }


    /**
     * Handle a a set of routes: if a match is found, execute
     * the relatinghandling function
     *
     * @param array $routes Collection of route patterns and their handling functions
     * @param bool $runOnce
     * @throws \BadFunctionCallException
     * @return int The number of routes handled
     */
    private function handle($routes, $runOnce = false)
    {
        // Counter to keep track of the number of routes we've handled
        $handled = 0;

        foreach ($routes as $pattern => $callable) {
            $pattern = strtr($pattern, $this->patternTokens);

            // Find matching route
            if (!preg_match('#^' . $pattern . '$#', $this->env['path'], $matches)) {
                continue;
            }

            // Remove the full matching path
            array_shift($matches);

            $callable = $this->getCallable($callable);

            // Call the handling function with the URL parameters
            call_user_func_array($callable, $matches);

            $handled++;

            // Only run one matching (for main routes)
            if ($runOnce) {
                break;
            }
        }

        return $handled;
    }


    /**
     * Execute the router, loop all defined middlewares and routes, and execute the handling function if matched
     *
     * @return int Matched route count
     */
    public function run()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        // Run before middlewares
        if (isset($this->beforeRoutes[$method])) {
            $this->handle($this->beforeRoutes[$method]);
        }

        // Run route
        $handled = 0;
        if (isset($this->routes[$method])) {
            $handled = $this->handle($this->routes[$method], true);
        }

        // Run after middlewares
        if (isset( $this->afterRoutes[$method] )) {
            $this->handle($this->afterRoutes[$method]);
        }

        // If no matching route
        if ($handled == 0) {
            header('HTTP/1.1 404 Not Found');

            if (isset($this->notFound)) {
                $callable = self::getCallable($this->notFound);
                call_user_func($callable);
            }
        }

        return $handled;
    }


    /**
     * Append a route and a handling function to route list
     *
     * @param array $routes Append route to this array
     * @param string $methods Allowed methods, `|` delimited
     * @param string $pattern A route pattern such as /about/system
     * @param callable $callable The handling function to be executed
     */
    private static function addRoute(&$routes, $methods, $pattern, $callable)
    {
        $pattern = '/' . trim($pattern, '/');

        foreach (explode('|', $methods) as $method) {
            $routes[$method][$pattern] = $callable;
        }
    }


    /**
     * Get the script URL path
     *
     * @return string
     */
    public function getScriptName()
    {
        return $this->env['script.name'];
    }


    /**
     * Get the script directory URL, without trailing slash '/'
     *
     * @return string
     */
    public function getScriptDir()
    {
        return $this->env['script.dir'];
    }


    /**
     * Return $uri append to the script URI
     * Example:
     *   '/login' => '/app/index.php/login' (without URL rewrite)
     *   '/login' => '/app/login' (with URL rewrite)
     *
     * @param string $uri URI relate to script path
     * @return string Absolute URI path
     */
    function path($uri)
    {
        return $this->getScriptName() . '/' . ltrim($uri, '/');
    }


    /**
     * Return $uri append to the script directory URI
     * Example: '/img/logo.jpg' => '/app/img/logo.jpg'
     *
     * @param string $uri URI relate to script directory
     * @return string Absolute URI path
     */
    function asset($uri)
    {
        return $this->getScriptDir() . '/' . ltrim($uri, '/');
    }


    /**
     * Get the path trailing the actual script URL
     *
     * @return string
     */
    public function getRequestMethod()
    {
        return $this->env['request.method'];
    }


    /**
     * Get the path trailing the actual script URL
     *
     * @return string
     */
    public function getPath()
    {
        return $this->env['path'];
    }


    /**
     * Store a before middleware route and a handling function to be executed
     * when accessed using one of the specified methods
     *
     * @param string $methods Allowed methods, `|` delimited
     * @param string $pattern A route pattern such as /about/system
     * @param callable $callable The handling function to be executed
     * @return self For chaining
     */
    public function before($methods, $pattern, $callable)
    {
        self::addRoute($this->beforeRoutes, $methods, $pattern, $callable);
        return $this;
    }


    /**
     * Store a after middleware route and a handling function to be executed
     * when accessed using one of the specified methods
     *
     * @param string $methods Allowed methods, `|` delimited
     * @param string $pattern A route pattern such as /about/system
     * @param callable|string $callable The handling function to be executed
     * @return self For chaining
     */
    public function after($methods, $pattern, $callable)
    {
        self::addRoute($this->afterRoutes, $methods, $pattern, $callable);
        return $this;
    }


    /**
     * Store a route and a handling function to be executed
     * when accessed using one of the specified methods
     *
     * @param string $methods Allowed methods, | delimited
     * @param string $pattern A route pattern such as /about/system
     * @param callable|string $callable The handling function to be executed
     * @return self For chaining
     */
    public function map($methods, $pattern, $callable)
    {
        self::addRoute($this->routes, $methods, $pattern, $callable);
        return $this;
    }


    /**
     * Route accessed using GET or POST
     *
     * @param string $pattern A route pattern such as /about/system
     * @param callable|string $callable The handling function to be executed
     * @return self For chaining
     */
    public function match($pattern, $callable)
    {
        self::addRoute($this->routes, 'GET|POST', $pattern, $callable);
        return $this;
    }


    /**
     * Route accessed using GET
     *
     * @param string $pattern A route pattern such as /about/system
     * @param callable|string $callable The handling function to be executed
     * @return self For chaining
     */
    public function get($pattern, $callable)
    {
        self::addRoute($this->routes, 'GET', $pattern, $callable);
        return $this;
    }


    /**
     * Route accessed using POST
     *
     * @param string $pattern A route pattern such as /about/system
     * @param callable|string $callable The handling function to be executed
     * @return self For chaining
     */
    public function post($pattern, $callable)
    {
        self::addRoute($this->routes, 'POST', $pattern, $callable);
        return $this;
    }


    /**
     * Shorthand for a route accessed using DELETE
     *
     * @param string $pattern A route pattern such as /about/system
     * @param callable|string $callable The handling function to be executed
     * @return self For chaining
     */
    public function delete($pattern, $callable)
    {
        self::addRoute($this->routes, 'DELETE', $pattern, $callable);
        return $this;
    }


    /**
     * Shorthand for a route accessed using PUT
     *
     * @param string $pattern A route pattern such as /about/system
     * @param callable|string $callable The handling function to be executed
     * @return self For chaining
     */
    public function put($pattern, $callable)
    {
        self::addRoute($this->routes, 'PUT', $pattern, $callable);
        return $this;
    }


    /**
     * Shorthand for a route accessed using OPTIONS
     *
     * @param string $pattern A route pattern such as /about/system
     * @param callable|string $callable The handling function to be executed
     * @return self For chaining
     */
    public function options($pattern, $callable)
    {
        self::addRoute($this->routes, 'OPTIONS', $pattern, $callable);
        return $this;
    }


    /**
     * Set the 404 handling function
     *
     * @param callable|string $callable The function to be executed
     * @return $this
     */
    public function setNotFound($callable) {
        $this->notFound = $callable;
        return $this;
    }


    /**
     * Redirect header
     *
     * @param string $uri Target URI
     * @param boolean $relative Append to script path
     * @param boolean $exit Exit after call
     */
    function redirect($uri, $relative = false, $exit = false)
    {
        if ($relative) {
            $uri = $this->path($uri);
        }

        header('Location: ' . $uri);

        if ($exit) {
            exit();
        }
    }
}
