<?php
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

class HttpServer {

  private $request_handler;

  public function __construct(RequestHandler $request_handler, array $options = null) {

    if ($options) {
      error_log('The "options" argument is not yet used, but may be used in the future');
    }

    if (!($request_handler instanceof RequestHandler)) {
      throw new \Exception('The request_handler argument should be an instance of RequestHandler');
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
    } catch (\Exception $error) {
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