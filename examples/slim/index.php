<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/blest/Router.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
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

$router = new Router();
$router->route('hello', $helloController);
$router->route('fail', $failController);
$router->use($authMiddleware);
$router->route('greet', $greetController);

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$app->options('/', function (Request $request, Response $response, $args) {
    return $response;
});

$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Origin, Content-Type, Authorization, Accept');
});

$app->post('/', function (Request $request, Response $response) use ($router) {
    $body = $request->getParsedBody();
    $context = [
      'headers' => $request->getHeaders()
    ];
    [$result, $error] = $router->handle($body, $context);
    if ($error) {
        $response->getBody()->write(json_encode($error));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } else {
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
});

$app->run();