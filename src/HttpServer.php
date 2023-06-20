<?php
namespace BLEST\BLEST;

require __DIR__ . '/include/polyfill.php';

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
      $this->response(null, 404);
      exit();
    }

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
      $this->response(null, 200);
    } else if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $this->response(null, 405);
      exit();
    }

    try {
      $data = json_decode(file_get_contents('php://input'), true);
    } catch (\Exception $error) {
      error_log($error->getMessage());
      $this->response(['message' => 'Unable to parse JSON body'], 400);
      exit();
    }

    if (!is_array($data) || !is_list($data)) {
      $this->response(['message' => 'Request body should be a JSON array'], 400);
      exit();
    }
    
    [$result, $error] = $this->request_handler->handle($data, []);
    
    if ($error) {
      $error_message = $error->getMessage();
      error_log($error_message);
      $this->response(['message' => $error_message], 500);
      exit();
    } else if ($result) {
      $this->response($result);
      exit();
    } else {
      $error_message = 'The request handler failed to return anything';
      error_log($error_message);
      $this->response(['message' => $error_message], 500);
      exit();
    }

  }

  private function cors_headers() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Origin, Content-Type, Accept');
  }

  private function response($data, $status_code = 200) {
    http_response_code($status_code);
    cors_headers();
    if ($data) {
      header('Content-Type: application/json');
      $json = json_encode($data);
      echo $json;
    }
  }

}