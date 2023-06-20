<?php
namespace BLEST\BLEST;

require __DIR__ . '/include/polyfill.php';

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
    $params = count($request) > 2 && is_array($request[2]) ? $request[2] : [];
    $selector = count($request) > 3 && is_list($request[3]) ? $request[3] : null;
    try {
      if (is_array($handler) && is_list($handler)) {
        $handler_steps = count($handler);
        for ($i = 0; $i < $handler_steps; $i++) {
          $temp_result = $handler[$i]($params, $context);
          if ($i === $handler_steps - 1) {
            $result = $temp_result;
          } else if ($temp_result) {
            throw new \Exception('Middleware should not return anything but may mutate context');
          }
        }
      } else if (is_callable($handler)) {
        $result = $handler($params, $context);
      } else {
        throw new \Exception('Route handler should be either a function or a list of functions');
      }
      if (!is_array($result) || is_list($result)) {
        throw new \Exception('Result should be an object');
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
      error_log($error->getMessage());
      return array(
        $id,
        $route,
        null,
        array('message' => $error->getMessage())
      );
    }
  }

  private function validate_request(array $request, array $unique_ids) {

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

    if (in_array($request[0], $unique_ids)) {
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

    $unique_ids = [];
    $promises = [];

    foreach ($requests as $request) {

      $validation_error = $this->validate_request($request, $unique_ids);
      
      if ($validation_error) {
        return self::handle_error(400, $validation_error);
      }

      $unique_ids[] = $request[0];

      $route_handler = array_key_exists($request[1], $this->routes) ? $this->routes[$request[1]] : null;

      if (!is_callable($route_handler) && !is_list($route_handler)) {
        $route_handler = function() {
          throw new \Exception('Route not found');
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