<?php

require __DIR__ . '/vendor/autoload.php';

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
    if (isset($context['headers']['auth']) && $context['headers']['auth'] === 'myToken') {
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

app->add(function ($request, $response) {
    $response->addHeader('Access-Control-Allow-Origin', '*');
    $response->addHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
    $response->addHeader('Access-Control-Allow-Headers', 'Origin, Content-Type, Authorization, Accept');
    return $response;
});

app()->post('/', function () use ($router) {
    $body = request()->body();
    $context = [
        'headers' => request()->headers()
    ];
    [$result, $error] = $router->handle($body);
    if ($error) {
        response()->json($error, 500);
    } else {
        response()->json($result, 200);
    }
});

app()->run();