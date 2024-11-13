<?php

require __DIR__ . '/vendor/autoload.php';

use OpenSwoole\Http\Server;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use BLEST\BLEST\Router;

$helloController = function() {
  return [
      'hello' => 'world',
      'bonjour' => 'le monde',
      'hola' => 'mundo',
      'hallo' => 'welt'
  ];
};

$authMiddleware = function($body, &$context) {
  if (isset($context['headers']) && $context['headers']['auth'] === 'myToken') {
      $context['user'] = [
        // user info for example
      ];
  } else {
      throw new Exception('Unauthorized');
  }
};

$greetController = function($body, $context) {
  return [
      'geeting' => 'Hi, ' . $body['name'] . '!'
  ];
};

$failController = function() {
  throw new Exception('Intentional failure');
};

$router = new BLEST\BLEST\Router();
$router->route('hello', $helloController);
$router->route('fail', $failController);
$router->use($authMiddleware);
$router->route('greet', $greetController);

$server = new OpenSwoole\HTTP\Server("localhost", 8080);

$server->on("start", function (OpenSwoole\Http\Server $server) {
  echo "OpenSwoole http server is started at http://127.0.0.1:8080\n";
});

$server->on("request", function (OpenSwoole\Http\Request $request, OpenSwoole\Http\Response $response) {
    global $router;
    if ($request->server['request_uri'] !== '/') {
        $response->status(404);
        $response->end('Not Found');
    } else {
        if ($request->server['request_method'] === 'POST') {
            $body = json_decode($request->getContent(), true);
            $context = [
                'http_headers' => $request->header ?: []
            ];
            [$result, $error] = $router->handle($body, $context);
            if ($error) {
                $response->header("Access-Control-Allow-Origin", "*");
                $response->header("Access-Control-Allow-Headers", "*");
                $response->header("Access-Control-Allow-Credentials", "true");
                $response->header("Access-Control-Allow-Methods", "GET, PUT, POST, DELETE, OPTIONS");
                $response->header("Accept", "application/json");
                $response->header("Content-Type", "application/json");
                $response->status(500);
                $response->end(json_encode($error));
            } else {
                $response->header("Access-Control-Allow-Origin", "*");
                $response->header("Access-Control-Allow-Headers", "*");
                $response->header("Access-Control-Allow-Credentials", "true");
                $response->header("Access-Control-Allow-Methods", "GET, PUT, POST, DELETE, OPTIONS");
                $response->header("Accept", "application/json");
                $response->header("Content-Type", "application/json");
                $response->end(json_encode($result));
            }
        } else if ($request->server['request_method'] === 'OPTIONS') {
            $response->header("Access-Control-Allow-Origin", "*");
            $response->header("Access-Control-Allow-Headers", "*");
            $response->header("Access-Control-Allow-Credentials", "true");
            $response->header("Access-Control-Allow-Methods", "GET, PUT, POST, DELETE, OPTIONS");
            $response->header("Accept", "application/json");
            $response->status(200);
        } else {
            $response->status(405);
            $response->end('Method Not Allowed');
        }
    }
});

$server->start();