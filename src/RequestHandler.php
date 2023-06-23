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

  private $routes;
  private $options;
  private $route_regex = '/^[a-zA-Z][a-zA-Z0-9_\-\/]*[a-zA-Z0-9_\-]$/';

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

  private function route_reducer($handler, array $request_object, array $context) {
    ['id' => $id, 'route' => $route, 'parameters' => $parameters, 'selector' => $selector] = $request_object;
    try {
      if (is_array($handler) && is_list($handler)) {
        $handler_steps = count($handler);
        for ($i = 0; $i < $handler_steps; $i++) {
          $temp_result = $handler[$i]($parameters, $context);
          if ($i === $handler_steps - 1) {
            $result = $temp_result;
          } else if ($temp_result) {
            throw new \Exception('Middleware should not return anything but may mutate context');
          }
        }
      } else if (is_callable($handler)) {
        $result = $handler($parameters, $context);
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

  public function handle(array $requests, array $context = []) {

    if (!$requests || !is_array($requests) || !is_list($requests)) {
      return self::handle_error(400, 'Request body should be a JSON array');
    }

    $unique_ids = [];
    $promises = [];

    foreach ($requests as $request) {

      if (!is_array($request) || !is_list($request)) {
        return handleError(400, 'Request item should be an array');
      }

      $id = isset($request[0]) ? $request[0] : null;
      $route = isset($request[1]) ? $request[1] : null;
      $parameters = isset($request[2]) ? $request[2] : null;
      $selector = isset($request[3]) ? $request[3] : null;

      if (!$id || !is_string($id)) {
          return handleError(400, 'Request item should have an ID');
      }
      if (!$route || !is_string($route)) {
          return handleError(400, 'Request item should have a route');
      }
      if (!preg_match($this->$route_regex, $route)) {
          $route_length = strlen($route);
          if ($routeLength < 2) {
              return handleError(400, 'Request item route should be at least two characters long');
          } elseif ($route[$route_length - 1] === '/') {
              return handleError(400, 'Request item route should not end in a forward slash');
          } elseif (!preg_match('/[a-zA-Z]/', $route[0])) {
              return handleError(400, 'Request item route should start with a letter');
          } else {
              return handleError(400, 'Request item route should contain only letters, numbers, dashes, underscores, and forward slashes');
          }
      }
      if ($parameters && !is_array($parameters)) {
          return handleError(400, 'Request item parameters should be a JSON object');
      }
      if ($selector && !is_list($selector)) {
          return handleError(400, 'Request item selector should be a JSON array');
      }

      if (in_array($id, $uniqueIds)) {
          return handleError(400, 'Request items should have unique IDs');
      }
      $uniqueIds[] = $id;

      $request_object = [
          'id' => $id,
          'route' => $route,
          'parameters' => $parameters,
          'selector' => $selector
      ];

      $route_handler = array_key_exists($route, $this->routes) ? $this->routes[$route] : null;

      if (!is_callable($route_handler) && !is_list($route_handler)) {
        $route_handler = function() {
          throw new \Exception('Route not found');
        };
      }

      $bound_handler = function() use ($route_handler, $request_object, $context) {
        return $this->route_reducer($route_handler, $request_object, $context);
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