<?php

require __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Middleware\BodyParsingMiddleware;
use Slim\Factory\AppFactory;
use BLEST\BLEST\Router;

$hello = function() {
    return [
        'hello' => 'world',
        'bonjour' => 'le monde',
        'hola' => 'mundo',
        'hallo' => 'welt'
    ];
};

$auth = function($params, &$context) {
    if ($params['name']) {
        $context['user'] = array(
            'name' => $params['name']
        );
    } else {
        throw new Exception('Unauthorized');
    }
};

$greet = function($params, $context) {
    if (!$context['user']['name']) {
        throw new Exception('Unauthorized');
    }
    return [
        'geeting' => 'Hi, ' . $context['user']['name'] . '!'
    ];
};

$fail = function() {
    throw new Exception('Intentional failure');
};

$router = new Router();
$router->route('hello', $hello);
$router->route('fail', $fail);
$router->use($auth);
$router->route('greet', $greet);

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