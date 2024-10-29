<?php
namespace BLEST\BLEST;

require_once __DIR__ . '/include/polyfill.php';
require_once __DIR__ . '/include/handler.php';
require_once __DIR__ . '/include/utilities.php';

class Router {
    private $introspection = false;
    private $middleware = [];
    private $afterware = [];
    private $timeout = 0;
    public $routes = [];

    public function __construct($options = []) {
        if (isset($options['introspection'])) {
            if (!is_bool($options['introspection'])) {
                throw new \Exception('Introspection should be a boolean');
            }
            $this->introspection = true;
        }
        if (isset($options['timeout'])) {
            if (!is_int($options['timeout']) || $options['timeout'] <= 0) {
                throw new \Exception('Timeout should be a positive integer');
            }
            $this->timeout = $options['timeout'];
        }
    }

    public function use(...$handlers) {
        foreach ($handlers as $handler) {
            if (!is_callable($handler)) {
                throw new \Exception('All arguments should be functions');
            }
            $argCount = (new \ReflectionFunction($handler))->getNumberOfParameters();
            if ($argCount <= 2) {
                $this->middleware[] = $handler;
            } else if ($argCount <= 3) {
                $this->afterware[] = $handler;
            } else {
                throw new \Exception('Middleware should have at most three arguments');
            }
        }
    }

    public function before(...$handlers) {
        foreach ($handlers as $handler) {
            if (!is_callable($handler)) {
                throw new \Exception('All arguments should be functions');
            }
            $argCount = (new \ReflectionFunction($handler))->getNumberOfParameters();
            if ($argCount <= 2) {
                $this->middleware[] = $handler;
            } else {
                throw new \Exception('Middleware should have at most two arguments');
            }
        }
    }

    public function after(...$handlers) {
        foreach ($handlers as $handler) {
            if (!is_callable($handler)) {
                throw new \Exception('All arguments should be functions');
            }
            $argCount = (new \ReflectionFunction($handler))->getNumberOfParameters();
            if ($argCount <= 3) {
                $this->afterware[] = $handler;
            } else {
                throw new \Exception('Afterware should have at most three arguments');
            }
        }
    }

    public function route($route, ...$args) {
        $last_arg = end($args);
        $options = is_callable($last_arg) ? null : $last_arg;
        $handlers = array_slice($args, 0, $options ? -1 : null);

        $route_error = validateRoute($route);
        if ($route_error) {
            throw new \Exception($route_error);
        } elseif (isset($this->routes[$route])) {
            throw new \Exception('Route already exists');
        } elseif (empty($handlers)) {
            echo json_encode($args);
            throw new \Exception('At least one handler is required');
        } elseif ($options !== null && !is_object($options)) {
            throw new \Exception('Last argument must be a configuration object or a handler function');
        } else {
            foreach ($handlers as $i => $handler) {
                if (!is_callable($handler)) {
                    throw new \Exception('Handlers must be functions: ' . $i);
                } elseif ((new \ReflectionFunction($handler))->getNumberOfParameters() > 2) {
                    throw new \Exception('Handlers should have at most two arguments');
                }
            }
        }

        $this->routes[$route] = [
            'handler' => array_merge($this->middleware, $handlers, $this->afterware),
            'description' => null,
            'schema' => null,
            'visible' => $this->introspection,
            'validate' => false,
            'timeout' => $this->timeout
        ];

        if ($options !== null) {
            $this->describe($route, $options);
        }
    }

    public function describe($route, $config) {
        if (!isset($this->routes[$route])) {
            throw new \Exception('Route does not exist');
        } elseif (!is_object($config)) {
            throw new \Exception('Configuration should be an object');
        }

        if (isset($config->description)) {
            if ($config->description && !is_string($config->description)) {
                throw new \Exception('Description should be a string');
            }
            $this->routes[$route]['description'] = $config->description;
        }

        if (isset($config->schema)) {
            if ($config->schema && !is_object($config->schema)) {
                throw new \Exception('Schema should be a JSON schema');
            }
            $this->routes[$route]['schema'] = $config->schema;
        }

        if (isset($config->visible)) {
            if (!in_array($config->visible, [true, false])) {
                throw new \Exception('Visible should be true or false');
            }
            $this->routes[$route]['visible'] = $config->visible;
        }

        if (isset($config->validate)) {
            if (!in_array($config->validate, [true, false])) {
                throw new \Exception('Visible should be true or false');
            }
            $this->routes[$route]['validate'] = $config->validate;
        }

        if (isset($config->timeout)) {
            if (!is_int($config->timeout) || !is_numeric($config->timeout) || $config->timeout <= 0) {
                throw new \Exception('Timeout should be a positive integer');
            }
            $this->routes[$route]['timeout'] = $config->timeout;
        }
    }

    public function merge($router) {
        if (!$router || !($router instanceof Router)) {
            throw new \Exception('Router is required');
        }

        $newRoutes = array_keys($router->routes);
        $existingRoutes = array_keys($this->routes);

        if (empty($newRoutes)) {
            throw new \Exception('No routes to merge');
        }

        foreach ($newRoutes as $route) {
            if (in_array($route, $existingRoutes)) {
                throw new \Exception('Cannot merge duplicate routes: ' . $route);
            } else {
                $this->routes[$route] = [
                    'handler' => array_merge(
                        $this->middleware,
                        $router->routes[$route]['handler'],
                        $this->afterware
                    ),
                    'description' => null,
                    'schema' => null,
                    'visible' => $router->routes[$route]['introspection'] ?? $this->introspection,
                    'validate' => false,
                    'timeout' => $router->routes[$route]['timeout'] ?? $this->timeout
                ];
                if (isset($router->routes[$route]['timeout']) || $router->timeout) {
                    $this->routes[$route]['timeout'] = $router->routes[$route]['timeout'] ?? $this->timeout;
                }
            }
        }
    }

    public function namespace($prefix, $router) {
        if (!$router || !($router instanceof Router)) {
            throw new \Exception('Router is required');
        }

        $prefixError = validateRoute($prefix);
        if ($prefixError) {
            throw new \Exception($prefixError);
        }

        $newRoutes = array_keys($router->routes);
        $existingRoutes = array_keys($this->routes);

        if (empty($newRoutes)) {
            throw new \Exception('No routes to namespace');
        }

        foreach ($newRoutes as $route) {
            $nsRoute = $prefix . '/' . $route;
            if (in_array($nsRoute, $existingRoutes)) {
                throw new \Exception('Route name already exists: ' . $nsRoute);
            } else {
                $this->routes[$nsRoute] = [
                    'handler' => array_merge(
                        $this->middleware,
                        $router->routes[$route]['handler'],
                        $this->afterware
                    ),
                    'description' => null,
                    'schema' => null,
                    'visible' => $router->routes[$route]['introspection'] ?? $this->introspection,
                    'validate' => false,
                    'timeout' => $router->routes[$route]['timeout'] ?? $this->timeout
                ];
            }
        }
    }

    public function handle($requests, $context = []) {
        return handle_request($this->routes, $requests, $context);
    }
}