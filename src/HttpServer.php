<?php
namespace BLEST\BLEST;

require_once __DIR__ . '/include/polyfill.php';

class HttpServer {

  private $url;
  private $httpHeaders;
  private $requestHandler;

  public function __construct(callable $requestHandler, array $options = []) {

    $this->url = isset($options['url']) ? $options['url'] : '/';

    $this->httpHeaders = [
        'access-control-allow-origin' => isset($options['accessControlAllowOrigin']) ? $options['accessControlAllowOrigin'] : (isset($options['cors']) ? (is_string($options['cors']) ? $options['cors'] : '*') : ''),
        'content-security-policy' => isset($options['contentSecurityPolicy']) ? $options['contentSecurityPolicy'] : "default-src 'self';base-uri 'self';font-src 'self' https: data:;form-action 'self';frame-ancestors 'self';img-src 'self' data:;object-src 'none';script-src 'self';script-src-attr 'none';style-src 'self' https: 'unsafe-inline';upgrade-insecure-requests",
        'cross-origin-opener-policy' => isset($options['crossOriginOpenerPolicy']) ? $options['crossOriginOpenerPolicy'] : 'same-origin',
        'cross-origin-resource-policy' => isset($options['crossOriginResourcePolicy']) ? $options['crossOriginResourcePolicy'] : 'same-origin',
        'origin-agent-cluster' => isset($options['originAgentCluster']) ? $options['originAgentCluster'] : '?1',
        'referrer-policy' => isset($options['referrerPolicy']) ? $options['referrerPolicy'] : 'no-referrer',
        'strict-transport-security' => isset($options['strictTransportSecurity']) ? $options['strictTransportSecurity'] : 'max-age=15552000; includeSubDomains',
        'x-content-type-options' => isset($options['xContentTypeOptions']) ? $options['xContentTypeOptions'] : 'nosniff',
        'x-dns-prefetch-control' => isset($options['xDnsPrefetchOptions']) ? $options['xDnsPrefetchOptions'] : 'off',
        'x-download-options' => isset($options['xDownloadOptions']) ? $options['xDownloadOptions'] : 'noopen',
        'x-frame-options' => isset($options['xFrameOptions']) ? $options['xFrameOptions'] : 'SAMEORIGIN',
        'x-permitted-cross-domain-policies' => isset($options['xPermittedCrossDomainPolicies']) ? $options['xPermittedCrossDomainPolicies'] : 'none',
        'x-xss-protection' => isset($options['xXssProtection']) ? $options['xXssProtection'] : '0'
    ];

    if (!is_callable($requestHandler)) {
      throw new \Exception('The request handler should be callable');
    }

    $this->requestHandler = $requestHandler;

  }

  public function run() {

    if ($_SERVER['REQUEST_URI'] !== $this->url) {
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

    $context = getallheaders();

    [$result, $error] = call_user_func($this->requestHandler, $data, $context);

    if ($error) {
      $errorMessage = $error['message'];
      error_log($errorMessage);
      $this->response(['message' => $errorMessage], 500);
      exit();
    } else if ($result) {
      $this->response($result);
      exit();
    } else {
      $errorMessage = 'The request handler failed to return anything';
      error_log($errorMessage);
      $this->response(['message' => $errorMessage], 500);
      exit();
    }

  }

  private function response($data, $statusCode = 200) {
    http_response_code($statusCode);
    foreach ($this->httpHeaders as $key => $value) {
      header($key . ': ' . $value);
    }
    if ($data) {
      header('Content-Type: application/json');
      $json = json_encode($data);
      echo $json;
    }
  }

}