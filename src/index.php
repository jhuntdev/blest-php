<?php
// -------------------------------------------------------------------------------------------------
// BLEST (Batch-able, Lightweight, Encrypted State Transfer) - A modern alternative to REST
// (c) 2023 JHunt <blest@jhunt.dev>
// License: MIT
// -------------------------------------------------------------------------------------------------
// Sample Request [id, endpoint, parameters (optional), selector (optional)]
// [
//   [
//     "abc123",
//     "math",
//     {
//       "operation": "divide",
//       "dividend": 22,
//       "divisor": 7
//     },
//     ["status",["result",["quotient"]]]
//   ]
// ]
// -------------------------------------------------------------------------------------------------
// Sample Response [id, endpoint, result, error (optional)]
// [
//   [
//     "abc123",
//     "math",
//     {
//       "status": "Successfully divided 22 by 7",
//       "result": {
//         "quotient": 3.1415926535
//       }
//     },
//     {
//       "message": "If there was an error you would see it here"
//     }
//   ]
// ]
// -------------------------------------------------------------------------------------------------

namespace BLEST\BLEST;

if (!function_exists('is_list')) {
  function is_list($value) {
    if (!is_array($value)) {
      return false;
    }
    $count = count($value);
    for ($i = 0; $i < $count; $i++) {
      if (!array_key_exists($i, $value)) {
        return false;
      }
    }
    return true;
  }
}

function filter_object(array $obj, array $arr): array {
  if (is_array($arr)) {
    $filteredObj = array();
    foreach ($arr as $key) {
      if (is_string($key)) {
        if (array_key_exists($key, $obj)) {
          $filteredObj[$key] = $obj[$key];
        }
      } elseif (is_array($key)) {
        $nestedObj = $obj[$key[0]];
        $nestedArr = $key[1];
        if (is_list($nestedObj)) {
          $filteredArr = array();
          foreach ($nestedObj as $nested) {
            $filteredNestedObj = filter_object($nested, $nestedArr);
            if (count($filteredNestedObj) > 0) {
              $filteredArr[] = $filteredNestedObj;
            }
          }
          if (count($filteredArr) > 0) {
            $filteredObj[$key[0]] = $filteredArr;
          }
        } elseif (is_array($nestedObj) && $nestedObj !== null) {
          $filteredNestedObj = filter_object($nestedObj, $nestedArr);
          if (count($filteredNestedObj) > 0) {
            $filteredObj[$key[0]] = $filteredNestedObj;
          }
        }
      }
    }
    return $filteredObj;
  }
  return array();
}

class HttpServer {

  private $request_handler;

  public function __construct(RequestHandler $request_handler, array $options = null) {

    if ($options) {
      error_log('The "options" argument is not yet used, but may be used in the future');
    }

    if (!($request_handler instanceof RequestHandler)) {
      throw new Exception('The request_handler argument should be an instance of RequestHandler');
    }

    $this->request_handler = $request_handler;

  }

  public function run() {

    header('Access-Control-Allow-Origin: *');

    if (!($_SERVER['REQUEST_URI'] == '/' || $_SERVER['REQUEST_URI'] == '')) {
      header('HTTP/1.1 404 Not Found');
      exit();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      header('HTTP/1.1 405 Method Not Allowed');
      exit();
    }

    try {
      $data = json_decode(file_get_contents('php://input'), true);
    } catch (Exception $error) {
      error_log($error->getMessage());
      header('HTTP/1.1 500 Internal Server Error');
      echo json_encode(['message' => 'Unable to parse JSON body']);
      exit();
    }

    if (!is_array($data) || !is_list($data)) {
      header('HTTP/1.1 400 Bad Request');
      echo json_encode(['message' => 'Request body should be a JSON array']);
      exit();
    }
    
    [$result, $error] = $this->request_handler->handle($data, []);
    
    if ($error) {
      header('HTTP/1.1 500 Internal Server Error');
      echo json_encode(['message' => $error->getMessage()]);
      exit();
    } else if ($result) {
      header('HTTP/1.1 200 OK');
      echo json_encode($result);
      exit();
    } else {
      header('HTTP/1.1 500 Internal Server Error');
      echo json_encode(['message' => 'The request handler failed to return anything']);
      exit();
    }

  }

}

class HttpClient {
  private $url;
  private $options;
  private $maxBatchSize = 100;
  private $queue = [];
  private $timeout = null;
  private $emitter;

  public function __construct($url, $options = null) {
    $this->url = $url;
    $this->options = $options;

    if ($this->options) {
      echo 'The "options" argument is not yet used, but may be used in the future.';
    }

    $this->emitter = new EventEmitter();
  }

  private function process() {
    $newQueue = array_splice($this->queue, 0, $this->maxBatchSize);
    if ($this->timeout !== null) {
      clearTimeout($this->timeout);
    }
    if (count($this->queue) === 0) {
      $this->timeout = null;
    } else {
      $this->timeout = setTimeout(function () {
        $this->process();
      }, 1);
    }

    $payload = json_encode($newQueue);
    $headers = [
      'Accept: application/json',
      'Content-Type: application/json'
    ];
    $options = [
      'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $payload
      ]
    ];
    $context = stream_context_create($options);
    $result = file_get_contents($this->url, false, $context);
    if ($result === false) {
      foreach ($newQueue as $q) {
        $this->emitter->emit($q[0], null, error_get_last());
      }
    } else {
      $data = json_decode($result);
      foreach ($data as $r) {
        $this->emitter->emit($r[0], $r[2], $r[3]);
      }
    }
  }

  public function request($route, $params = null, $selector = null) {
    return new Promise(function ($resolve, $reject) use ($route, $params, $selector) {
      if (!$route) {
        return $reject(new Exception('Route is required'));
      } elseif ($params && !is_array($params)) {
        return $reject(new Exception('Params should be an array'));
      } elseif ($selector && !is_array($selector)) {
        return $reject(new Exception('Selector should be an array'));
      }

      $id = uniqid();
      $this->emitter->once($id, function ($result, $error) use ($resolve, $reject) {
        if ($error) {
          $reject($error);
        } else {
          $resolve($result);
        }
      });
      $this->queue[] = [$id, $route, $params ?: null, $selector ?: null];
      if ($this->timeout === null) {
        $this->timeout = setTimeout(function () {
          $this->process();
        }, 1);
      }
    });
  }
}

class EventEmitter {
  private $listeners = [];

  public function on($event, $callback) {
    if (!isset($this->listeners[$event])) {
      $this->listeners[$event] = [];
    }
    $this->listeners[$event][] = $callback;
  }

  public function emit($event, ...$args) {
    if (isset($this->listeners[$event])) {
      foreach ($this->listeners[$event] as $listener) {
        call_user_func_array($listener, $args);
      }
    }
  }

  public function once($event, $callback) {
    $onceCallback = function (...$args) use ($event, $callback) {
      $this->removeListener($event, $onceCallback);
      call_user_func_array($callback, $args);
    };
    $this->on($event, $onceCallback);
  }

  public function removeListener($event, $callback) {
    if (isset($this->listeners[$event])) {
      $index = array_search($callback, $this->listeners[$event], true);
      if ($index !== false) {
        unset($this->listeners[$event][$index]);
      }
    }
  }
}

class RequestHandler {

  public function __construct(array $routes, array $options = null) {

    if ($options) {
      error_log('The "options" argument is not yet used, but may be used in the future');
    }

    $this->routes = $routes;
    $this->options = $options;
  
  }

  private static function handle_result(array $result) {
    return array($result, null);
  }

  private static function handle_error(int $code, string $message) {
    return array(null, array(
      'code' => $code,
      'message' => $message
    ));
  }

  private function route_reducer($handler, array $request, array $context) {
    $id = $request[0];
    $route = $request[1];
    $params = count($request) > 2 ? $request[2] : null;
    $selector = count($request) > 3 ? $request[3] : null;
    try {
      if (is_array($handler) && is_list($handler)) {
        $handler_steps = count($handler);
        for ($i = 0; $i < $handler_steps; $i++) {
          $temp_result = $handler[$i]($params, $context);
          if ($i === $handler_steps - 1) {
            $result = $temp_result;
          } else if ($temp_result) {
            throw new Exception('Middleware should not return anything but may mutate context');
          }
        }
      } else if (is_callable($handler)) {
        $result = $handler($params, $context);
      } else {
        throw new Exception('Route handler should be either a function or a list of functions');
      }
      if (!is_array($result) || is_list($result)) {
        throw new Exception('Result should be an object');
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
    } catch (Exception $error) {
      error_log($error->getMessage());
      return array(
        $id,
        $route,
        null,
        array('message' => $error->getMessage())
      );
    }
  }

  private function validate_request(array $request, array $dedupe) {

    if (
      !is_array($request) ||
      !is_list($request) ||
      count($request) < 2 ||
      gettype($request[0]) !== 'string' ||
      gettype($request[1]) !== 'string'
    ) {
      return 'Request items should be an array with a unique ID and an endpoint';
    }

    if (isset($request[2])) {
      if (
        !is_array($request[2]) ||
        is_list($request[2])
      ) {
        return 'Request item parameters should be a JSON object';
      }
    }

    if (isset($request[3])) {
      if (
        !is_array($request[3]) ||
        !is_list($request[3])
      ) {
        return 'Request item selector should be a JSON array';
      }
    }

    if (in_array($request[0], $dedupe)) {
      return 'Request items should have unique IDs';
    }

    return null;

  }

  private $routes;
  private $options;

  public function handle(array $requests, array $context = []) {

    if (!$requests || !is_array($requests) || !is_list($requests)) {
      return self::handle_error(400, 'Request body should be a JSON array');
    }

    $dedupe = [];
    $promises = [];

    foreach ($requests as $request) {

      $validation_error = $this->validate_request($request, $dedupe);
      
      if ($validation_error) {
        return self::handle_error(400, $validation_error);
      }

      $dedupe[] = $request[0];

      $route_handler = array_key_exists($request[1], $this->routes) ? $this->routes[$request[1]] : null;

      if (!is_callable($route_handler)) {
        $route_handler = function() {
          throw new Exception('Route not found');
        };
      }

      $bound_handler = function() use ($route_handler, $request, $context) {
        return $this->route_reducer($route_handler, $request, $context);
      };

      $promises[] = $bound_handler;

    }

    $results = [];
    
    foreach ($promises as $promise) {
      $results[] = $promise();
    }

    return self::handle_result($results);

  }

}