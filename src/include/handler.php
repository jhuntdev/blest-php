<?php

require_once __DIR__ . '/polyfill.php';
require_once __DIR__ . '/utilities.php';

function handle_result(array $result) {
    return array($result, null);
}

function handle_error(int $status, string $message) {
    return array(null, array(
        'status' => $status,
        'message' => $message
    ));
}

function handle_request(array $routes, array $requests, array $context = []) {

    if (!$requests || !is_list($requests)) {
        return handle_error(400, 'Request body should be a JSON array');
    }

    $uniqueIds = [];
    // $promises = [];

    foreach ($requests as $request) {

        if (!is_list($request)) {
            return handle_error(400, 'Request item should be an array');
        }

        $id = isset($request[0]) ? $request[0] : null;
        $route = isset($request[1]) ? $request[1] : null;
        $body = isset($request[2]) ? $request[2] : null;
        $headers = isset($request[3]) ? $request[3] : null;

        if (!$id || !is_string($id)) {
            return handle_error(400, 'Request item should have an ID');
        }
        if (!$route || !is_string($route)) {
            return handle_error(400, 'Request item should have a route');
        }
        if ($body && !is_array($body)) {
            return handle_error(400, 'Request item body should be a JSON object');
        }
        if ($headers && !is_list($headers)) {
            return handle_error(400, 'Request item headers should be a JSON object');
        }

        if (in_array($id, $uniqueIds)) {
            return handle_error(400, 'Request items should have unique IDs');
        }
        $uniqueIds[] = $id;

        $requestObject = [
            'id' => $id,
            'route' => $route,
            'body' => $body,
            'headers' => $headers
        ];

        $requestContext = array_merge($context, [
            'request' => $requestObject,
            'time' => time()
        ]);

        $thisRoute = array_key_exists($route, $routes) ? $routes[$route] : null;
        $routeHandler = $thisRoute ? $thisRoute['handler'] : null;
        $routeTimeout = $thisRoute ? $thisRoute['timeout'] : 0;

        if (!is_list($routeHandler)) {
            $routeHandler = [function() {
                throw new \Exception('Route not found');
            }];
        }

        if ($routeTimeout) {
            $results[] = routeReducerWithTimeout($routeHandler, $requestObject, $requestContext, $routeTimeout);
        } else {
            $results[] = routeReducer($routeHandler, $requestObject, $requestContext);
        }

    }

    return handle_result($results);

}

function routeReducerWithTimeout($handler, array $request, array $context, int $timeout = 0) {
    $id = $request['id'];
    $route = $request['route'];
    $timedOut = false;
    pcntl_signal(SIGALRM, function () use (&$timedOut) {
        $timedOut = true;
    });
    pcntl_alarm(ceil($timeout / 1000));
    $output = routeReducer($handler, $request, $context);
    pcntl_alarm(0);
    pcntl_signal(SIGALRM, SIG_DFL);
    if ($timedOut) {
        return [$id, $route, null, ['message' => 'Internal Server Error', 'status' => 500]];
    } else {
        return $output;
    }
}


function routeReducer($handler, array $request, array $context) {
    $id = $request['id'];
    $route = $request['route'];
    $body = isset($request['body']) ? $request['body'] : [];
    try {
        $result = null;
        $error = null;
        if (is_list($handler)) {
            $handler_steps = count($handler);
            for ($i = 0; $i < $handler_steps; $i++) {
                $reflection = new \ReflectionFunction($handler[$i]);
                $numArgs = $reflection->getNumberOfParameters();
                if ($error) {
                    if ($numArgs <= 2) continue;
                } else {
                    if ($numArgs > 2) continue;
                }
                try {
                    if ($error) {
                        $tempResult = $handler[$i]($body, $context, $error);
                    } else {
                        $tempResult = $handler[$i]($body, $context);
                    }
                } catch (\Exception $temp_err) {
                    if (!$error) {
                        $error = $temp_err;
                    }
                }
                if (!$error && $tempResult) {
                    if ($result) {
                        throw new \Exception('Internal Server Error');
                    } else {
                        $result = $tempResult;
                    }
                }
            }
        } else {
            throw new \Exception('Route handler should be a list of functions');
        }
        if ($error) {
            $responseError = assemble_error($error);
            return [$id, $route, null, $responseError];
        }
        if (!$result || !is_array($result) || is_list($result)) {
            // echo "The route \"$route\" did not return a result object\n";
            return [$id, $route, null, ['message' => 'Internal Server Error', 'status' => 500]];
        }
        // if ($selector) {
        //     $result = filter_object($result, $selector);
        // }
        return array(
            $id,
            $route,
            $result,
            null
        );
    } catch (\Exception $error) {
        $responseError = assemble_error($error);
        return [$id, $route, null, $responseError];
    }
}

function assemble_error($error) {
    if ($error instanceof Exception) {
        $error = [
            'message' => $error->getMessage(),
            'code' => $error->getCode(),
            'stack' => $error->getTrace(),
        ];
    }
    $responseError = array(
        'message' => isset($error['message']) ? $error['message'] : 'Internal Server Error',
        'status' => isset($error['status']) ? $error['status'] : 500,
    );

    if (isset($error['code']) && !!$error['code']) {
        $responseError['code'] = $error['code'];
    }

    if (isset($error['data']) && !!$error['data']) {
        $responseError['data'] = $error['data'];
    }

    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] !== 'production' && isset($error['stack'])) {
        $responseError['stack'] = $error['stack'];
    }

    return $responseError;
}