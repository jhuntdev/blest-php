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

    $unique_ids = [];
    $promises = [];

    foreach ($requests as $request) {

        if (!is_list($request)) {
            return handle_error(400, 'Request item should be an array');
        }

        $id = isset($request[0]) ? $request[0] : null;
        $route = isset($request[1]) ? $request[1] : null;
        $parameters = isset($request[2]) ? $request[2] : null;
        $selector = isset($request[3]) ? $request[3] : null;

        if (!$id || !is_string($id)) {
            return handle_error(400, 'Request item should have an ID');
        }
        if (!$route || !is_string($route)) {
            return handle_error(400, 'Request item should have a route');
        }
        if ($parameters && !is_array($parameters)) {
            return handle_error(400, 'Request item parameters should be a JSON object');
        }
        if ($selector && !is_list($selector)) {
            return handle_error(400, 'Request item selector should be a JSON array');
        }

        if (in_array($id, $unique_ids)) {
            return handle_error(400, 'Request items should have unique IDs');
        }
        $unique_ids[] = $id;

        $request_object = [
            'id' => $id,
            'route' => $route,
            'parameters' => $parameters,
            'selector' => $selector
        ];

        $this_route = array_key_exists($route, $routes) ? $routes[$route] : null;
        $route_handler = $this_route ? $this_route['handler'] : null;
        $route_timeout = $this_route ? $this_route['timeout'] : 0;

        if (!is_list($route_handler)) {
            $route_handler = [function() {
                throw new \Exception('Route not found');
            }];
        }

        if ($route_timeout) {
            $results[] = route_reducer_with_timeout($route_handler, $request_object, $context, $route_timeout);
        } else {
            $results[] = route_reducer($route_handler, $request_object, $context);
        }

    }

    return handle_result($results);

}

function route_reducer_with_timeout($handler, array $request, array $context, int $timeout = 0) {
    $id = $request['id'];
    $route = $request['route'];
    $timed_out = false;
    pcntl_signal(SIGALRM, function () use (&$timed_out) {
        $timed_out = true;
    });
    pcntl_alarm(ceil($timeout / 1000));
    $output = route_reducer($handler, $request, $context);
    pcntl_alarm(0);
    pcntl_signal(SIGALRM, SIG_DFL);
    if ($timed_out) {
        return [$id, $route, null, ['message' => 'Internal Server Error', 'status' => 500]];
    } else {
        return $output;
    }
}


function route_reducer($handler, array $request, array $context) {
    $id = $request['id'];
    $route = $request['route'];
    $parameters = $request['parameters'];
    $selector = $request['selector'];
    try {
        $result = null;
        $error = null;
        if (is_list($handler)) {
            $handler_steps = count($handler);
            for ($i = 0; $i < $handler_steps; $i++) {
                $reflection = new \ReflectionFunction($handler[$i]);
                $num_args = $reflection->getNumberOfParameters();
                if ($error) {
                    if ($num_args <= 2) continue;
                } else {
                    if ($num_args > 2) continue;
                }
                try {
                    if ($error) {
                        $temp_result = $handler[$i]($parameters, $context, $error);
                    } else {
                        $temp_result = $handler[$i]($parameters, $context);
                    }
                } catch (\Exception $temp_err) {
                    if (!$error) {
                        $error = $temp_err;
                    }
                }
                if (!$error && $temp_result) {
                    if ($result) {
                        throw new \Exception('Internal Server Error');
                    } else {
                        $result = $temp_result;
                    }
                }
            }
        } else {
            throw new \Exception('Route handler should be a list of functions');
        }
        if ($error) {
            $response_error = assemble_error($error);
            return [$id, $route, null, $response_error];
        }
        if (!$result || !is_array($result) || is_list($result)) {
            // echo "The route \"$route\" did not return a result object\n";
            return [$id, $route, null, ['message' => 'Internal Server Error', 'status' => 500]];
        }
        if ($selector) {
            $result = filter_object($result, $selector);
        }
        return array(
            $id,
            $route,
            $result,
            null
        );
    } catch (\Exception $error) {
        $response_error = assemble_error($error);
        return [$id, $route, null, $response_error];
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
    $response_error = array(
        'message' => isset($error['message']) ? $error['message'] : 'Internal Server Error',
        'status' => isset($error['status']) ? $error['status'] : 500,
    );

    if (isset($error['code']) && !!$error['code']) {
        $response_error['code'] = $error['code'];
    }

    if (isset($error['data']) && !!$error['data']) {
        $response_error['data'] = $error['data'];
    }

    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] !== 'production' && isset($error['stack'])) {
        $response_error['stack'] = $error['stack'];
    }

    return $response_error;
}