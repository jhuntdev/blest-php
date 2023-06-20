<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Middleware\BodyParsingMiddleware;
use Slim\Factory\AppFactory;
use BLEST\BLEST\RequestHandler;

require __DIR__ . '/vendor/autoload.php';

$hello = function($params, $context) {
    return [
        'hello' => 'world',
        'bonjour' => 'le monde',
        'hola' => 'mundo',
        'hallo' => 'welt'
    ];
};

$auth = function($params, $context) {
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

$fail = function($params, $context) {
    throw new Exception('Intentional failure');
};

$request_handler = new RequestHandler([
    'hello' => $hello,
    'greet' => [$auth, $greet],
    'fail' => $fail
]);

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$app->post('/', function (Request $request, Response $response) use ($request_handler) {
    $body = $request->getParsedBody();
    [$result, $error] = $request_handler->handle($body);
    if ($error) {
        $response->getBody()->write(json_encode($error));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    } else {
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
});

$app->run();